<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Node;

use LC\Common\Config;
use LC\Common\ProfileConfig;
use RuntimeException;

/**
 * Detect IPv4 and IPv6 range overlaps.
 */
class ConfigCheck
{
    /**
     * @return void
     */
    public static function verify(array $profileList)
    {
        // make sure profileNumber is unique for all profiles
        $profileNumberList = [];
        $listenProtoPortList = [];
        $rangeList = [];
        foreach ($profileList as $profileId => $profileConfigData) {
            $profileConfig = new ProfileConfig(new Config($profileConfigData));

            // make sure profileNumber is not reused in multiple profiles
            $profileNumber = $profileConfig->profileNumber();
            if (\in_array($profileNumber, $profileNumberList, true)) {
                throw new RuntimeException(sprintf('"profileNumber" (%d) in profile "%s" already used', $profileNumber, $profileId));
            }
            $profileNumberList[] = $profileNumber;

            // make sure the listen/port/proto is unique
            $listenAddress = $profileConfig->listen();
            $vpnProtoPorts = $profileConfig->vpnProtoPorts();
            foreach ($vpnProtoPorts as $vpnProtoPort) {
                $listenProtoPort = $listenAddress.' -> '.$vpnProtoPort;
                if (\in_array($listenProtoPort, $listenProtoPortList, true)) {
                    throw new RuntimeException(sprintf('"listen/vpnProtoPorts combination "%s" in profile "%s" already used before', $listenProtoPort, $profileId));
                }
                $listenProtoPortList[] = $listenProtoPort;
            }

            // network bits required for all processes
            $prefixSpace = log(\count($vpnProtoPorts), 2);

            // make sure "range" is 29 or lower for each OpenVPN process
            // (OpenVPN server limitation)
            $rangeFour = $profileConfig->range();
            list($ipRange, $ipPrefix) = explode('/', $rangeFour);
            if ((int) $ipPrefix > (29 - $prefixSpace)) {
                throw new RuntimeException(sprintf('"range" in profile "%s" MUST be at least "/%d" to accommodate %d OpenVPN server process(es)', $profileId, 29 - $prefixSpace, \count($vpnProtoPorts)));
            }
            $rangeList[] = $rangeFour;

            // make sure "range6" is 112 or lower for each OpenVPN process
            // (OpenVPN server limitation)
            $rangeSix = $profileConfig->range6();
            list($ipRange, $ipPrefix) = explode('/', $rangeSix);
            // we ALSO want the prefix to be divisible by 4 (restriction in
            // IP.php)
            if (0 !== ((int) $ipPrefix) % 4) {
                throw new RuntimeException(sprintf('prefix length of "range6" in profile "%s" MUST be divisible by 4', $profileId, $ipPrefix));
            }
            if ((int) $ipPrefix > (112 - $prefixSpace)) {
                throw new RuntimeException(sprintf('"range6" in profile "%s" MUST be at least "/%d" to accommodate %d OpenVPN server process(es)', $profileId, 112 - $prefixSpace, \count($vpnProtoPorts)));
            }
            $rangeList[] = $rangeSix;

            // make sure dnsSuffix is not set (anymore)
            $dnsSuffix = $profileConfig->dnsSuffix();
            if (0 !== \count($dnsSuffix)) {
                echo 'WARNING: "dnsSuffix" is deprecated. Please use "dnsDomain" and "dnsDomainSearch" instead'.PHP_EOL;
            }
        }

        // Check for IPv4/IPv6 range overlaps between profiles
        $overlapList = self::checkOverlap($rangeList);
        if (0 !== \count($overlapList)) {
            foreach ($overlapList as $o) {
                echo sprintf('WARNING: IP range %s overlaps with IP range %s', $o[0], $o[1]).PHP_EOL;
            }
        }
    }

    /**
     * Check whether any of the provided IP ranges in IP/prefix notation
     * overlaps any of the others.
     *
     * @param array<string> $ipRangeList
     *
     * @return array<array{0:string, 1:string}>
     */
    public static function checkOverlap(array $ipRangeList)
    {
        $overlapList = [];
        $minMaxFourList = [];
        $minMaxSixList = [];
        foreach ($ipRangeList as $ipRange) {
            if (false === strpos($ipRange, ':')) {
                // IPv4
                self::getMinMax($minMaxFourList, $overlapList, $ipRange);
            } else {
                // IPv6
                self::getMinMax($minMaxSixList, $overlapList, $ipRange);
            }
        }

        return $overlapList;
    }

    /**
     * @param string $ipRange
     *
     * @return void
     */
    private static function getMinMax(array &$minMaxList, array &$overlapList, $ipRange)
    {
        list($ipAddress, $ipPrefix) = explode('/', $ipRange);
        $binIp = self::ipToBin($ipAddress);
        $minIp = substr($binIp, 0, (int) $ipPrefix).str_repeat('0', \strlen($binIp) - (int) $ipPrefix);
        $maxIp = substr($binIp, 0, (int) $ipPrefix).str_repeat('1', \strlen($binIp) - (int) $ipPrefix);
        foreach ($minMaxList as $minMax) {
            if ($minIp >= $minMax[0] && $minIp <= $minMax[1]) {
                $overlapList[] = [$ipRange, $minMax[2]];

                continue;
            }
            if ($maxIp >= $minMax[0] && $maxIp <= $minMax[1]) {
                $overlapList[] = [$ipRange, $minMax[2]];

                continue;
            }
        }
        $minMaxList[] = [$minIp, $maxIp, $ipRange];
    }

    /**
     * Convert an IP address to its binary representation to make it easy to
     * do string operations on the strings of length 32 (IPv4) and 128 (IPv6)
     * regarding determining the first/last host of the prefix to be able to do
     * string compare for overlap detection. This is quite ugly :-).
     *
     * @param string $ipAddr
     *
     * @return string
     */
    private static function ipToBin($ipAddr)
    {
        $hexStr = bin2hex(inet_pton($ipAddr));
        $binStr = '';
        // base_convert does not work with arbitrary length input, so here we
        // limit it to 32 bits
        for ($i = 0; $i < \strlen($hexStr) / 8; ++$i) {
            $binStr .= str_pad(
                base_convert(
                    substr($hexStr, $i * 8, 8),
                    16,
                    2
                ),
                32,
                '0',
                STR_PAD_LEFT
            );
        }

        return $binStr;
    }
}
