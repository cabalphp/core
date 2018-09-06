<?php
namespace Cabal\Core\Base;

use Cabal\Core\Server;
use Cabal\Core\Http\Request;
use Valitron\Validator;
use Cabal\Core\Exception\BadRequestException;
use Cabal\Core\ChainExecutor;
use Cabal\Core\Exception\RequestInvalidException;


class DocumentController
{
    protected $classRules = [];

    public function getMarkdown(Server $server, Request $request, $vars = [])
    {
        $filename = $vars['filename'];
        $filename = rawurldecode($filename);
        if (method_exists($this, strtolower($filename))) {
            $filename = strtolower($filename);
            return $this->$filename($server);
        }

        $modules = $this->apis($server);

        if (isset($modules[$filename])) {
            $lines = [
                "# {$filename}"
            ];
            foreach ($modules[$filename] as $path => $api) {
                $lines[] = sprintf("\r\n## %s\r\n", $api['title']);
                $lines[] = rtrim($api['description']);
                $lines[] = "\r\n";
                $lines[] = "\r\n##### 接口地址";
                $lines[] = sprintf("方法：`%s`\r\n", implode('` `', (array)$api['method']), $api['path']);
                $lines[] = sprintf("    %s\r\n", $api['path']);

                $lines[] = "\r\n##### 接口参数";
                if (isset($api['params']) && $api['params']) {
                    $table = [];
                    $table[] = sprintf('| %s | %s | %s | %s | %s |', '参数名', '类型', '默认值', '约束', '描述');
                    $columnLengths = [];
                    foreach ($api['params'] as $paramName => $paramInfo) {
                        if (count($table) < 2) {
                            $table[] = sprintf(
                                "| %s | %s | %s | %s | %s |",
                                str_repeat('-', strlen($paramName)),
                                str_repeat('-', strlen($paramInfo['type'])),
                                str_repeat('-', strlen($paramInfo['default'])),
                                str_repeat('-', strlen($paramInfo['constraint'])),
                                str_repeat('-', strlen($paramInfo['description']))
                            );
                        }
                        if (strpos($paramName, '.') !== false) {
                            $paramName = str_repeat('&nbsp;', substr_count($paramName, '.') * 4) . $paramName;
                        }
                        $table[] = sprintf("| %s | %s | %s | %s | %s |", $paramName, $paramInfo['type'], $paramInfo['default'] ? : '', $paramInfo['constraint'], $paramInfo['description']);
                    }
                    $table = implode("\r\n", $table);
                    $lines[] = $table;
                }
                //
                foreach ([
                    'success' => '成功',
                    'error' => '错误'
                ] as $key => $name) {
                    if (isset($api[$key]) && $api[$key]) {
                        $lines[] = "\r\n##### {$name}返回字段";
                        $table = [];
                        $table[] = sprintf('| %s | %s | %s |', '字段', '类型', '描述');
                        foreach ($api[$key] as $returnParam) {
                            $returnParam = preg_split("~[ ]+~", rtrim($returnParam));
                            $paramType = array_shift($returnParam);
                            $paramName = array_shift($returnParam);
                            $paramDesc = implode(" ", $returnParam);
                            if (count($table) < 2) {
                                $table[] = sprintf(
                                    "| %s | %s | %s |",
                                    str_repeat('-', strlen($paramName)),
                                    str_repeat('-', strlen($paramType)),
                                    str_repeat('-', strlen($paramDesc))
                                );
                            }

                            if (strpos($paramName, '.') !== false) {
                                $paramName = str_repeat('&nbsp;', substr_count($paramName, '.') * 4) . $paramName;
                            }
                            $table[] = sprintf("| %s | %s | %s |", $paramName, $paramType, $paramDesc);
                        }
                        $table = implode("\r\n", $table);
                        $lines[] = $table;

                    }

                    if (isset($api["{$key}Example"]) && $api["{$key}Example"]) {
                        foreach ($api["{$key}Example"] as $returnExample) {
                            $returnExample = trim($returnExample);
                            $returnExample = explode(' ', $returnExample);
                            do {
                                $exampleFormat = trim(array_shift($returnExample));
                            } while (!$exampleFormat && count($returnExample) > 0);

                            do {
                                $exampleTitle = trim(array_shift($returnExample));
                            } while (!$exampleTitle && count($returnExample) > 0);

                            $returnExample = trim(implode(" ", $returnExample));
                            $lines[] = "\r\n##### {$name}返回示例 - {$exampleTitle}";
                            $lines[] = sprintf("```%s\r\n%s\r\n```", $exampleFormat, $returnExample);
                        }
                    }

                }
            }
            return implode("\r\n", $lines);
        }
        return '# 文档不存在' . $filename;

    }


    /**
     * Undocumented function
     *
     * @apiIgnore 1
     */
    public function getIndex(Server $server, Request $request, $vars = [])
    {
        $cdn = $server->configure('cabal.document.cdn', 'unpkg.com');
        $projectName = $server->configure('cabal.document.name', 'CabalPHP');
        ob_start();
        require dirname(dirname(__DIR__)) . '/res/views/docs.php';
        $html = ob_get_clean();
        return $html;
    }

    protected function readme($server)
    {
        $md = '# 请在项目根目录新建 DOCS.md';
        if (file_exists($server->rootPath('DOCS.md'))) {
            $md = file_get_contents($server->rootPath('DOCS.md'));
        }
        return $md;
    }

    protected function _sidebar($server)
    {
        $modules = $this->apis($server);
        $lines = [];
        $lines[] = sprintf('* %s', 'API文档');
        foreach ($modules as $module => $apis) {
            $lines[] = sprintf('  * [%s](/%s.md)', $module, $module);
        }
        return implode("\r\n", $lines);
    }

    protected function apis($server)
    {
        $ruleLangs = null;
        $ruleLangName = $server->configure('cabal.validator.lang', 'zh-cn');
        $ruleLangDir = $server->configure('cabal.validator.langDir');
        $ruleLangFile = $ruleLangDir . DIRECTORY_SEPARATOR . $ruleLangName . '.php';
        if (file_exists($ruleLangFile)) {
            $ruleLangs = require $ruleLangFile;
        }

        $routes = $server->getDispatcher()->getRoute()->getRoutesRaw();
        $apis = [];
        foreach ($routes as $route) {
            list($method, $path, $handler) = $route;
            if ($handler['handler'] instanceof \Closure || !$handler['handler']) {
                continue;
            } elseif (is_string($handler['handler']) && strpos($handler['handler'], '@') !== false) {
                list($className, $methodName) = explode('@', $handler['handler']);
            } elseif (is_string($handler['handler']) && strpos($handler['handler'], '::') !== false) {
                list($className, $methodName) = explode('::', $handler['handler']);
            } else {
                continue;
            }
            if ($method === 'WS' && strtolower($methodName) == 'on') {
                $methodName = 'onMessage';
            }
            $reflectionClass = new \ReflectionClass($className);
            if ($reflectionClass->hasMethod($methodName)) {
                $reflectionMethod = $reflectionClass->getMethod($methodName);
                $comments = $this->parseComment($reflectionMethod->getDocComment());
                if (!$comments) {
                    continue;
                }
                $rules = [];

                if (is_subclass_of($className, FilterController::class)) {
                    if (!isset($this->classRules[$className])) {
                        $controller = new $className();
                        $this->classRules[$className] = $controller->rules();;
                    }

                    $classRules = $this->classRules[$className];
                    $rules = isset($classRules[$methodName]) ? $classRules[$methodName] : [];
                }
                $rules = array_map(function ($rule) use ($ruleLangs) {
                    return $this->parseRule($rule, $ruleLangs);
                }, $rules);

                $apis[] = [
                    'method' => $method,
                    'path' => $path,
                    'rules' => $rules,
                    'comments' => $comments,
                ];
            } else {
                // $apis[] = [
                //     'method' => $method,
                //     'path' => $path,
                // ];
            }
        }
        $apis = $this->formatApis($apis);
        return $apis;
    }

    protected function formatApis($apis)
    {
        $modules = [];
        foreach ($apis as &$api) {
            $params = [];
            foreach ([
                'title' => 'apiTitle',
                'description' => 'apiDescription',
                'module' => 'apiModule',
                'ignore' => 'apiIgnore',
            ] as $key => $tag) {
                if (isset($api['comments'][$tag])) {
                    $api[$key] = implode("\r\n", $api['comments'][$tag]);
                }
            }

            foreach ([
                'success' => 'apiSuccess',
                'error' => 'apiError',
                'successExample' => 'apiSuccessExample',
                'errorExample' => 'apiErrorExample',
            ] as $key => $tag) {
                if (isset($api['comments'][$tag])) {
                    $api[$key] = $api['comments'][$tag];
                }
            }
            if (isset($api['comments']['apiParam'])) {
                foreach ($api['comments']['apiParam'] as $paramDesc) {
                    $paramDesc = preg_split("~[ ]+~", trim($paramDesc));
                    $paramName = array_shift($paramDesc);
                    $paramType = array_shift($paramDesc);
                    $paramDesc = implode(' ', $paramDesc);
                    $default = null;
                    if (strpos($paramName, '=') !== false) {
                        list($paramName, $default) = explode('=', $paramName);
                    }


                    if (!isset($params[$paramName])) {
                        $params[$paramName] = [
                            'type' => 'string',
                            'constraint' => '-',
                        ];
                    }
                    $params[$paramName]['type'] = $paramType;
                    $params[$paramName]['description'] = str_replace(["\r", "\n"], '', nl2br($paramDesc));
                    $params[$paramName]['default'] = $default;
                }
                unset($api['comments']);
            }
            if (isset($api['rules'])) {
                foreach ($api['rules'] as $paramName => $constraint) {
                    if (!isset($params[$paramName])) {
                        $params[$paramName] = [
                            'type' => 'string',
                            'description' => '-',
                            'default' => '',
                        ];
                    }
                    $params[$paramName]['constraint'] = $constraint;
                }
                unset($api['rules']);
            }
            if (count($params) > 0) {
                $api['params'] = $params;
            }
            if (count($api) > 0) {
                $api = array_merge([
                    'title' => $api['path'],
                    'description' => '',
                    'module' => '未分类',
                ], $api);
                if (!isset($api['ignore']) || !$api['ignore']) {
                    if (!isset($modules[$api['module']])) {
                        $modules[$api['module']] = [];
                    }
                    $modules[$api['module']][] = $api;
                }
            }


        }
        return $modules;
    }

    protected function parseComment($comment)
    {
        if (!$comment) {
            return [];
        }
        $result = [];
        $lines = explode("\n", $comment);
        $lines = array_slice($lines, 1, -1);

        $tag = 'apiTitle';
        $tagContent = [];
        foreach ($lines as $no => $line) {
            $line = strpos($line, '*') !== false ? substr($line, strpos($line, '*') + 2) : $line;
            if (substr(ltrim($line), 0, 1) === '@') {
                $line = explode(" ", ltrim($line));
                if (count($tagContent) > 0) {
                    if (!isset($result[$tag])) {
                        $result[$tag] = [];
                    }
                    $result[$tag][] = implode("\r\n", $tagContent);
                }
                $tagContent = [];

                $tag = trim(array_shift($line), '@ ');
                $line = implode(" ", $line);
            }
            $tagContent[] = $line;
        }
        if (count($tagContent) > 0) {
            if (!isset($result[$tag])) {
                $result[$tag] = [];
            }
            $result[$tag][] = implode("\r\n", $tagContent);
        }

        return $result;
    }

    protected function parseRule($rules, $langs = [])
    {
        $langs = $langs ? : [
            'required' => "不能为空",
            'equals' => "必须和 '%s' 一致",
            'different' => "必须和 '%s' 不一致",
            'accepted' => "必须接受",
            'numeric' => "只能是数字",
            'integer' => "只能是整数",
            'length' => "长度必须大于 %d",
            'min' => "必须大于 %s",
            'max' => "必须小于 %s",
            'in' => "无效的值",
            'notIn' => "无效的值",
            'ip' => "无效IP地址",
            'email' => "无效邮箱地址",
            'url' => "无效的URL",
            'urlActive' => "必须是可用的域名",
            'alpha' => "只能包括英文字母(a-z)",
            'alphaNum' => "只能包括英文字母(a-z)和数字(0-9)",
            'slug' => "只能包括英文字母(a-z)、数字(0-9)、破折号和下划线",
            'regex' => "无效格式",
            'date' => "无效的日期",
            'dateFormat' => "日期的格式应该为 '%s'",
            'dateBefore' => "日期必须在 '%s' 之前",
            'dateAfter' => "日期必须在 '%s' 之后",
            'contains' => "必须包含 %s",
            'boolean' => "必须是真或假",
            'lengthBetween' => "长度只能介于 %d 和 %d 之间",
            'creditCard' => "信用卡号码不正确",
            'lengthMin' => "长度必须大于 %d",
            'lengthMax' => "长度必须小于 %d"
        ];

        $description = [];
        foreach ($rules as $rule) {
            if (is_array($rule)) {
                $ruleName = array_shift($rule);
                if (isset($langs[$ruleName])) {
                    $description[] = sprintf($langs[$ruleName], ...$rule);
                } else {
                    $description[] = $ruleName . ':' . json_encode($rule);
                }
            } else {
                if (isset($langs[$rule])) {
                    $description[] = sprintf($langs[$rule]);
                } else {
                    $description[] = $rule;
                }
            }
        }
        return implode('; ', $description);
    }

}