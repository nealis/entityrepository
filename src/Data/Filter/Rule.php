<?php

namespace Nealis\EntityRepository\Data\Filter;

class Rule
{
	const EQUALS = 'equals';
	const MINOR = 'minor';
	const MAJOR = 'major';
	const MINOREQUALS = 'minorEquals';
	const MAJOREQUALS = 'majorEquals';
	const NOT = 'not';
	const ISNULL = 'isNull';
	const NOTNULL = 'notNull';
	const IN = 'in';
	const NOTIN = 'notIn';
	const BETWEEN = 'between';
	const CONTAINS = 'contains';
	const NOTCONTAINS = 'notContains';
	const BEGINSWITH = 'beginsWith';
	const NOTBEGINSWITH = 'notBeginsWith';
	const ENDSWITH = 'endsWith';
	const NOTENDSWITH = 'notEndsWith';

    const PLACEHOLDER = '?';

	protected $field;
	protected $operator;
	protected $data;

	public function __construct($field, string $operator, array $data = [])
	{
		$this->field = $field;
        $this->operator = $operator;
		$this->data = $data;
	}

	public function getField()
	{
		return $this->field;
	}

	public function getOperator()
	{
		return $this->operator;
	}

	public function getData()
	{
		return $this->data;
	}

	public function setField(string $field)
	{
		$this->field = $field;
		return $this;
	}

	public function setOperator(string $operator)
	{
        if($operator === null) {
            throw new \Exception("Rule operator can't be null");
        }
		$this->operator = $operator;
		return $this;
	}

    public function setData(array $data = [])
	{
		if(!is_array($data)) {
		    $data = [$data];
        }
		$this->data = $data;
		return $this;
	}

	public function toArray()
    {
		return [
			"field" => $this->field,
			"op" => $this->operator,
			"data" => $this->data,
		];
	}

    public static function getRuleWhere(array $rule)
    {
        $field = $rule['field'];
        $op = $rule['op'];
        $data = $rule['data'];

        return self::getWhere($field, $op, $data);
    }

    public static function getRuleWhereVal(array $rule)
    {
        $op = $rule['op'];
        $data = $rule['data'];

        return static::getWhereVal($op, $data);
    }

    public static function getWhere(string $field, string $op = Rule::EQUALS, $data = [])
    {
        $where = "";
        if(!is_array($data)) $data = [$data];

        switch ($op) {
            case Rule::EQUALS: {
                $where .= $field."=".static::PLACEHOLDER;

                break;
            }
            case Rule::MINOR: {
                $where .= $field."<".static::PLACEHOLDER;

                break;
            }
            case Rule::MAJOR: {
                $where .= $field.">".static::PLACEHOLDER;

                break;
            }
            case Rule::MINOREQUALS: {
                $where .= $field."<=".static::PLACEHOLDER;

                break;
            }
            case Rule::MAJOREQUALS: {
                $where .= $field.">=".static::PLACEHOLDER;

                break;
            }
            case Rule::NOT: {
                $where .= $field."<> ".static::PLACEHOLDER;

                break;
            }
            case Rule::ISNULL: {
                $where .= $field." IS NULL";

                break;
            }
            case Rule::NOTNULL: {
                $where .= $field." IS NOT NULL";

                break;
            }
            case Rule::IN: {
                $where .= $field." IN (";
                $questionMarks = array();
                foreach ($data as $d) {
                    array_push($questionMarks, static::PLACEHOLDER);
                }
                $where .= implode(",", $questionMarks).")";

                break;
            }
            case Rule::NOTIN: {
                $where .= $field." NOT IN (";
                $questionMarks = array();
                foreach ($data as $d) {
                    array_push($questionMarks, static::PLACEHOLDER);
                }
                $where .= implode(",", $questionMarks).")";

                break;
            }
            case Rule::BETWEEN: {
                $where .= $field." BETWEEN ".static::PLACEHOLDER." AND ".static::PLACEHOLDER;

                break;
            }
            case Rule::CONTAINS: {
                if (str_replace('%', '', $data[0]) !== null)
                    $where .= $field." LIKE ".static::PLACEHOLDER;

                break;
            }
            case Rule::NOTCONTAINS: {
                if (str_replace('%', '', $data[0]) !== null)
                    $where .= $field." NOT LIKE ".static::PLACEHOLDER;

                break;
            }
            case Rule::BEGINSWITH: {
                if (str_replace('%', '', $data[0]) !== null)
                    $where .= $field." LIKE ".static::PLACEHOLDER;

                break;
            }
            case Rule::NOTBEGINSWITH: {
                if (str_replace('%', '', $data[0]) !== null)
                    $where .= $field." NOT LIKE ".static::PLACEHOLDER;

                break;
            }
            case Rule::ENDSWITH: {
                if (str_replace('%', '', $data[0]) !== null)
                    $where .= $field." LIKE ".static::PLACEHOLDER;

                break;
            }
            case Rule::NOTENDSWITH: {
                if (str_replace('%', '', $data[0]) !== null)
                    $where .= $field." NOT LIKE ".static::PLACEHOLDER;

                break;
            }
            default: {
                throw new \Exception(sprintf("Operation '%s' is not managed by the filter query generator", $op));
            }
        }

        return $where;
    }

    public static function getWhereVal(string $op, $data = [])
    {
        $whereVal = [];
        if(!is_array($data)) $data = [$data];

        switch ($op) {
            case Rule::EQUALS: {
                array_push($whereVal, $data[0]);

                break;
            }
            case Rule::MINOR: {
                array_push($whereVal, $data[0]);

                break;
            }
            case Rule::MAJOR: {
                array_push($whereVal, $data[0]);

                break;
            }
            case Rule::MINOREQUALS: {
                array_push($whereVal, $data[0]);

                break;
            }
            case Rule::MAJOREQUALS: {
                array_push($whereVal, $data[0]);

                break;
            }
            case Rule::NOT: {
                array_push($whereVal, $data[0]);

                break;
            }
            case Rule::ISNULL: {

                break;
            }
            case Rule::NOTNULL: {

                break;
            }
            case Rule::IN: {

                foreach ($data as $d) {
                    array_push($whereVal, $d);
                }

                break;
            }
            case Rule::NOTIN: {

                foreach ($data as $d) {
                    array_push($whereVal, $d);
                }

                break;
            }
            case Rule::BETWEEN: {
                array_push($whereVal, $data[0]);
                array_push($whereVal, $data[1]);

                break;
            }
            case Rule::CONTAINS: {
                if (str_replace('%', '', $data[0] !== null))
                    array_push($whereVal, "%".$data[0]."%");

                break;
            }
            case Rule::NOTCONTAINS: {
                if (str_replace('%', '', $data[0] !== null))
                    array_push($whereVal, "%".$data[0]."%");

                break;
            }
            case Rule::BEGINSWITH: {
                if (str_replace('%', '', $data[0] !== null))
                    array_push($whereVal, "".$data[0]."%");

                break;
            }
            case Rule::NOTBEGINSWITH: {
                if (str_replace('%', '', $data[0] !== null))
                    array_push($whereVal, "".$data[0]."%");

                break;
            }
            case Rule::ENDSWITH: {
                if (str_replace('%', '', $data[0] !== null))
                    array_push($whereVal, "%".$data[0]."");

                break;
            }
            case Rule::NOTENDSWITH: {
                if (str_replace('%', '', $data[0] !== null))
                    array_push($whereVal, "%".$data[0]."");

                break;
            }
            default: {
                throw new \Exception("Operation ERROR");
            }
        }

        return $whereVal;
    }
}
