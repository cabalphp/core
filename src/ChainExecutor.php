<?php
namespace Cabal\Core;

interface ChainExecutor
{
    public function execute($method, $params = []);
}