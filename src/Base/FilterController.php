<?php
namespace Cabal\Core\Base;

use Cabal\Core\Server;
use Cabal\Core\Http\Request;
use Valitron\Validator;
use Cabal\Core\Exception\BadRequestException;
use Cabal\Core\ChainExecutor;
use Cabal\Core\Exception\RequestInvalidException;


class FilterController implements ChainExecutor
{
    public function rules()
    {
        return [];
    }

    public function execute($method, $params = [])
    {
        $rules = $this->rules();
        $rules = isset($rules[$method]) ? $rules[$method] : [];
        array_unshift($params, $rules);
        $this->filter(...$params);
        array_shift($params);
        return $this->$method(...$params);
    }

    public function filter($rules, Server $server, Request $request, $vars = [])
    {
        Validator::lang($server->configure('cabal.validator.lang', 'zh-cn'));
        Validator::langDir($server->configure('cabal.validator.langDir'));

        $params = $request->only(array_keys($rules));
        $validator = new Validator($params);
        $labels = [];
        foreach ($rules as $paramName => &$rule) {
            if (isset($rule['label'])) {
                $labels[$paramName] = $rule['label'];
                unset($rule['label']);
            }
        }
        $validator->mapFieldsRules($rules);
        if (count($labels) > 0) {
            $validator->labels($labels);
        }

        if ($validator->validate()) {
            return true;
        } else {
            $messages = [];
            foreach ($validator->errors() as $field => $fieldMessages) {
                $messages = array_merge($messages, $fieldMessages);
            }
            throw new BadRequestException($messages);
        }
    }

}