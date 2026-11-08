<?php
// Test environment only: OpenCart 1.5 needs mcrypt, which PHP 7.2+ no longer has (hosts patch this file the same way).
final class Encryption {
	private $key;
	public function __construct($key) { $this->key = hash('sha256', $key, true); }
	public function encrypt($value) { return strtr(base64_encode(openssl_encrypt($value, 'aes-256-ecb', $this->key, OPENSSL_RAW_DATA)), '+/=', '-_,'); }
	public function decrypt($value) { return trim(openssl_decrypt(base64_decode(strtr($value, '-_,', '+/=')), 'aes-256-ecb', $this->key, OPENSSL_RAW_DATA)); }
}
