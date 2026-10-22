<?php

namespace App\Traits;

trait EncryptTrait
{
    public function encryptKey($string, $salt = null)
    {

        if($salt === null) { $salt = hash('sha256',true); }
        return base64_encode(openssl_encrypt($string, 'AES-256-CBC', config('app.encrypt_key'), 0, str_pad(substr($salt, 0, 16), 16, '0', STR_PAD_LEFT))).':'.$salt;

    }

    public function decryptKey($string)
    {

        if( count(explode(':', $string)) !== 2 ) { return $string; }
        $salt = explode(":",$string)[1]; $string = explode(":",$string)[0]; // read salt from entry
        return openssl_decrypt(base64_decode($string), 'AES-256-CBC', config('app.encrypt_key'), 0, str_pad(substr($salt, 0, 16), 16, '0', STR_PAD_LEFT));

    }

    public function encryptData($data, $key) {

        $iv = openssl_random_pseudo_bytes(16);

        // Encrypt the data with AES-128-CBC mode
        $encryptedData = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        // Combine IV and encrypted data, then Base64 encode it
        $encryptedWithIv = $iv . $encryptedData;
        $encodedData = base64_encode($encryptedWithIv);

        return $encodedData;
    }


    public function decryptData($encodedData, $key) {

        $encryptedWithIv = base64_decode($encodedData);

        // Extract the first 16 bytes as IV
        $iv = substr($encryptedWithIv, 0, 16);
        $encryptedData = substr($encryptedWithIv, 16);

        // Decrypt the data with AES-128-CBC mode
        $decryptedData = openssl_decrypt($encryptedData, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decryptedData;

    }

    /**
     * Encrypt data untuk URL (tidak mengandung karakter / + =)
     * Menggunakan base64url encoding yang aman untuk URL
     *
     * @param string $data Data yang akan dienkripsi
     * @param string $key Kunci enkripsi
     * @return string Enkripsi URL-safe (base64url)
     */
    public function encryptDataURL($data, $key) {

        $iv = openssl_random_pseudo_bytes(16);

        // Encrypt the data with AES-128-CBC mode
        $encryptedData = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        // Combine IV and encrypted data
        $encryptedWithIv = $iv . $encryptedData;

        // Encode dengan base64url (URL-safe)
        $encodedData = $this->base64urlEncode($encryptedWithIv);

        return $encodedData;
    }

    /**
     * Decrypt data dari encryptDataURL
     *
     * @param string $encodedData Data yang sudah dienkripsi (base64url)
     * @param string $key Kunci enkripsi
     * @return string Data asli
     */
    public function decryptDataURL($encodedData, $key) {

        // Decode dari base64url
        $encryptedWithIv = $this->base64urlDecode($encodedData);

        // Extract the first 16 bytes as IV
        $iv = substr($encryptedWithIv, 0, 16);
        $encryptedData = substr($encryptedWithIv, 16);

        // Decrypt the data with AES-128-CBC mode
        $decryptedData = openssl_decrypt($encryptedData, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $decryptedData;
    }

    /**
     * Base64 URL-Safe Encoding
     * Mengganti / dengan -, + dengan _, dan menghilangkan padding =
     *
     * @param string $data Data yang akan di-encode
     * @return string Base64url encoded string
     */
    private function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-Safe Decoding
     *
     * @param string $data Base64url encoded string
     * @return string Decoded data
     */
    private function base64urlDecode($data)
    {
        // Tambahkan kembali padding jika diperlukan
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }

        // Ubah URL-safe characters kembali ke base64
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
