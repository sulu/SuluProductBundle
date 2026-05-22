<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Sulu\Component\HttpKernel\SuluKernel;
use Sulu\Product\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Dotenv())->bootEnv(\dirname(__DIR__) . '/Application/.env');

$env = $_SERVER['APP_ENV'] ?? 'dev';
if (!\is_string($env)) {
    throw new \RuntimeException('APP_ENV must be a string.');
}
$kernel = new Kernel($env, (bool) $_SERVER['APP_DEBUG'], SuluKernel::CONTEXT_ADMIN);
$kernel->boot();

return new Application($kernel);
