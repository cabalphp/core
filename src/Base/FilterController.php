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
        return $this->$method(...$params);
    }

    public function filter($rules, Server $server, Request $request, $vars = [])
    {
        Validator::lang($server->configure('cabal.validator.lang', 'zh-cn'));
        Validator::langDir($server->configure('cabal.validator.langDir'));

        $validator = new Validator($request->only(array_keys($rules)));
        $validator->mapFieldsRules($rules);

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