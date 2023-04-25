<?php

namespace Itwmw\Validate\Attributes\Mysql;

/**
 * @internal
 */
class FixToArrayFunc
{
    public function __construct(protected string $phpCode)
    {
    }

    public function fix(): string
    {
        /** @var ArrayData[] $fields */
        $fields                 = [];
        $tokens                 = token_get_all($this->phpCode);
        $toArrayFuncReturnIndex = 0;
        $arrayData              = new ArrayData();
        $returnArrayLine        = 0;
        foreach ($tokens as  $token) {
            if (is_array($token)) {
                if (T_STRING === $token[0] && 'toArray' === $token[1]) {
                    $toArrayFuncReturnIndex = 1;
                    continue;
                }

                if (T_RETURN === $token[0] && 1 === $toArrayFuncReturnIndex) {
                    $toArrayFuncReturnIndex = 2;
                    $returnArrayLine        = $token[2];
                    continue;
                }

                if (3 === $toArrayFuncReturnIndex) {
                    if (T_CONSTANT_ENCAPSED_STRING === $token[0]) {
                        $arrayData->name = $token[1];
                    } elseif (T_VARIABLE === $token[0]) {
                        $arrayData->value = $token[1];
                    } elseif (T_OBJECT_OPERATOR === $token[0] || T_STRING === $token[0]) {
                        $arrayData->value = $arrayData->value . $token[1];
                    }
                    continue;
                }
            }

            if ('[' === $token && 2 === $toArrayFuncReturnIndex) {
                $toArrayFuncReturnIndex = 3;
            }

            if (3 === $toArrayFuncReturnIndex && (']' === $token || ',' === $token)) {
                $fields[] = $arrayData;
                if (']' === $token) {
                    break;
                }
                $arrayData = new ArrayData();
            }
        }

        $php = ['return ['];
        foreach ($fields as $field) {
            if (!empty($field->value)) {
                $php[] = sprintf('    %s => %s,', $field->name, $field->value);
            } else {
                $php[] = sprintf('    %s,', $field->name);
            }

        }
        $php[] = '];';
        $codes = explode("\n", $this->phpCode);
        unset($codes[$returnArrayLine - 1]);
        array_splice($codes, $returnArrayLine - 1, 0, $php);
        return implode(PHP_EOL, $codes);
    }
}
