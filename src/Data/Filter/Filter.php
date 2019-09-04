<?php

namespace Nealis\EntityRepository\Data\Filter;

class Filter
{
    const GLUE_AND  = 'AND';
    const GLUE_OR   = 'OR';

    protected $rules = [];
    protected $groups = [];
    protected $glue = Filter::GLUE_AND;
    protected $not = false;

    protected $defaultRuleOperator = Rule::EQUALS;

    /**
     * Filter constructor.
     * @param array|string $filters
     * @param string $defaultRuleOperator
     */
    public function __construct($filters = [], string $defaultRuleOperator = null)
    {
        if($defaultRuleOperator !== null) {
            $this->setDefaultRuleOperator($defaultRuleOperator);
        }
        if (!empty($filters)) {
            $this->initFilters($filters);
        }
    }

    /**
     * @param array $filters
     */
    private function initFilters($filters)
    {
        if (array_key_exists('rules', $filters) || array_key_exists('groups', $filters) || array_key_exists('not', $filters)) {
            $this->initGroup($filters);
        } else {
            foreach ($filters as $fieldName => $fieldData) {
                if(is_array($fieldData) && array_key_exists('field', $fieldData)) {
                    $this->initRule($fieldData);
                } else {
                    $this->addRule($fieldName, $this->getDefaultRuleOperator(), $fieldData);
                }
            }
        }
    }

    public function initGroup($filters)
    {
        if (array_key_exists('rules', $filters)) {
            $rules = $filters['rules'];
            $this->initRules($rules);

            if (!empty($rulesArray)) {
                $this->setGlue($filters['groupOp']);
            }
        }
        if (array_key_exists('groups', $filters)) {
            $groups = $filters['groups'];
            foreach ($groups as $key => $group) {
                $className = get_class($this);
                $groupsArray[] = new $className($group);
            }
            if (!empty($groupsArray)) {
                $this->setGroups($groupsArray);
            }
        }

        if (array_key_exists('not', $filters)) {
            $this->setNot($filters['not']);
        }
    }

    /**
     * @param string $fieldName
     * @param string $ruleOperator
     * @return $this
     * @throws \Exception
     */
    public function convertFilterRule(string $fieldName, string $ruleOperator)
    {
        foreach ($this->rules as &$rule) {
            if ($rule->getField() === $fieldName) {
                $rule->setOperator($ruleOperator);
            }
        }
        //TODO test me
        return $this;
    }

    private function initRules(array $rules)
    {
        foreach ($rules as $key => $rule) {
            $this->initRule($rule);
        }
    }

    private function initRule(array $rule)
    {
        $this->addRule($rule['field'], $rule['op'], $rule['data']);
    }

    public function getRules() {
        return $this->rules;
    }

    public function getGlue() {
        return $this->glue;
    }

    public function getGroups() {
        return $this->groups;
    }

    public function setRules(array $rules) {
        $this->rules = $rules;
        return $this;
    }

    public function setGlue(string $glue) {
        $this->glue = $glue;
        return $this;
    }

    public function setGroups(array $groups) {
        $this->groups = $groups;
        return $this;
    }

    public function pushRule(Rule $rule)
    {
        $this->rules[] = $rule;
        return $this;
    }

    public function addRuleEquals(string $field, array $data = null)
    {
        $this->addRule($field, Rule::EQUALS, $data);
        return $this;
    }

    public function addGroup(array $group)
    {
        //TODO test me
        array_push($this->getGroups(), $group);
        return $this;
    }

    public function toArray()
    {
        $groupArray = [];

        $rulesArray = [];
        $rules = $this->getRules();

        foreach ($rules as $key => $rule) {
            $rulesArray[] = $rule->toArray();
        }

        if(!empty($rulesArray)) {
            $groupArray['groupOp'] = $this->getGlue();
            $groupArray['rules'] = $rulesArray;
        }

        //subGroups
        $groupsArray = [];
        $groups = $this->getGroups();

        foreach ($groups as $key => $group) {
            $groupsArray[] = $group->toArray();
        }

        if(!empty($groupsArray)) {
            $groupArray['groupOp'] = $this->getGlue();
            $groupArray['groups'] = $groupsArray;
        }

        if(!empty($rulesArray) || !empty($groupsArray)) {
            $groupArray['not'] = $this->isNot();
        }

        return $groupArray;
    }

    public function toSQLWhere()
    {
        return static::getWhere($this->toArray());
    }

    public function toSQLWhereVal()
    {
        return static::getWhereVal($this->toArray());
    }

    public function getDefaultRuleOperator()
    {
        return $this->defaultRuleOperator;
    }

    public function setDefaultRuleOperator(string $defaultRuleOperator)
    {
        $this->defaultRuleOperator = $defaultRuleOperator;
        return $this;
    }

    public function isNot()
    {
        return $this->not;
    }

    public function setNot(bool $not = true)
    {
        $this->not = $not;
        return $this;
    }

    public static function getWhere($filters)
    {
        if(empty($filters)) return '';

        $where = '';
        $whereArray = [];

        //TODO Filters as Class
        $groupOp = $filters['groupOp'];

        $rules = array_key_exists('rules', $filters) ? $filters['rules'] : [];
        $groups = array_key_exists('groups', $filters) ? $filters['groups'] : [];
        $not = array_key_exists('not', $filters) && $filters['not'];

        if(!empty($rules)) {
            $whereArray = self::parseRules($rules);
        }

        if(!empty($groups)) {
            $whereArray = self::parseGroups($groups);
        }

        $glue = " $groupOp ";
        $where .= implode($glue, $whereArray);
        if($not) {
            $where = ' NOT('.$where.') ';
        }

        return $where;
    }

    public static function getWhereVal(array $filters)
    {
        if(empty($filters)) return [];

        $whereVal = [];

        if(isset($filters['rules'])) $rules = $filters['rules'];
        if(isset($filters['groups'])) $groups = $filters['groups'];

        if(!empty($rules)) {
            foreach ($rules as $rule) {
                $whereVals = Rule::getRuleWhereVal($rule);
                foreach ($whereVals as $wV) {
                    array_push($whereVal, $wV);
                }
            }
        }

        if(!empty($groups)) {
            foreach ($groups as $group) {
                $whereVals = static::getWhereVal($group);
                foreach ($whereVals as $wV) {
                    array_push($whereVal, $wV);
                }
            }
        }
        return $whereVal;
    }

    public static function getWhereArray(array $filters)
    {
        if(empty($filters)) return [];

        $whereArray = [];

        if(isset($filters['rules'])) $rules = $filters['rules'];
        if(isset($filters['groups'])) $groups = $filters['groups'];

        if(!empty($rules)) {
            foreach ($rules as $rule) {
                $whereArray[Rule::getRuleWhere($rule)] = Rule::getRuleWhereVal($rule);
            }
        }

        if(!empty($groups)) {
            throw new \Exception('getWhereArray does not support sub groups');
        }
        return $whereArray;
    }

    private static function parseRules(array $rules, array $whereArray = [])
    {
        foreach ($rules as $rule) {
            $ruleWhere = Rule::getRuleWhere($rule);
            if (!empty($ruleWhere)) {
                array_push($whereArray, $ruleWhere);
            }
        }
        return $whereArray;
    }

    private static function parseGroups(array $groups, array $whereArray = [])
    {
        foreach ($groups as $group) {
            $tempWhere = static::getWhere($group);
            if (!$group['not']) {
                $tempWhere = ' (' . $tempWhere . ') ';
            }
            array_push($whereArray, $tempWhere);
        }
        return $whereArray;
    }

    public function addRule($field, ?string $operator = null, array $data = null)
    {
        //TODO verify signature
        if(is_null($operator)) {
            $operator = $this->getDefaultRuleOperator();
        }
        $this->pushRule(new Rule($field, $operator, $data));
        return $this;
    }

    public static function getInstance($filters = null, $defaultRuleOperator = null)
    {
        return new Filter($filters, $defaultRuleOperator);
    }
}
