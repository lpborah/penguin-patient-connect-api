<?php
declare(strict_types=1);

namespace App;

class Utility
{
    /**
     * Generate a random alphanumeric string
     * 
     * @param int $length Length of the generated string
     * @return string
     */
    public static function generateRandomString(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    // Get IP Address
    public static function getClientIp(): ?string
    {
        $ip_address = '';
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			// Check for shared internet/ISP IP
			$ip_address = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// Check for IPs passing through proxies
			$multiple_ips = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip_address = trim(current($multiple_ips));
		} else {
			// Most reliable, the direct connection IP
			$ip_address = $_SERVER['REMOTE_ADDR'];
		}
		return $ip_address;
    }

}
