#!/usr/bin/env php
<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\Http\InputValidation;
use SURFnet\VPN\Common\HttpClient\GuzzleHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Node\Otp;

$logger = new Logger(
    basename($argv[0])
);

try {
    $envData = [];
    $envKeys = [
        'INSTANCE_ID',
        'PROFILE_ID',
        'common_name',
        'username',
        'password',
    ];

    // read environment variables
    foreach ($envKeys as $envKey) {
        $envData[$envKey] = getenv($envKey);
    }

    $instanceId = InputValidation::instanceId($envData['INSTANCE_ID']);
    $configDir = sprintf('%s/config/%s', dirname(__DIR__), $instanceId);
    $config = Config::fromFile(
        sprintf('%s/config.yaml', $configDir)
    );

    // vpn-server-api
    $serverClient = new ServerClient(
        new GuzzleHttpClient(
            [
                'defaults' => [
                    'auth' => [
                        $config->v('apiProviders', 'vpn-server-api', 'userName'),
                        $config->v('apiProviders', 'vpn-server-api', 'userPass'),
                    ],
                ],
            ]
        ),
        $config->v('apiProviders', 'vpn-server-api', 'apiUri')
    );

    $otp = new Otp($logger, $serverClient);
    if (false === $otp->verify($envData)) {
        exit(1);
    }
} catch (Exception $e) {
    $logger->error($e->getMessage());
    exit(1);
}