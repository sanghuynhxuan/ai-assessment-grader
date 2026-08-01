<?php

namespace LearnDashAIGrader\Core;

class Encryption {

    private static $method = 'AES-256-CBC';


    private static function get_key() {
        if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
            return LOGGED_IN_KEY;
        }
        return md5( get_site_url() );
    }

    public static function encrypt( $string ) {
        if ( empty( $string ) ) return '';
        
        $key = substr( hash( 'sha256', self::get_key() ), 0, 32 );
        $iv = substr( hash( 'sha256', 'iv_' . self::get_key() ), 0, 16 );
        
        $encrypted = openssl_encrypt( $string, self::$method, $key, 0, $iv );
        return base64_encode( $encrypted );
    }

    public static function decrypt( $string ) {
        if ( empty( $string ) ) return '';
        
        $key = substr( hash( 'sha256', self::get_key() ), 0, 32 );
        $iv = substr( hash( 'sha256', 'iv_' . self::get_key() ), 0, 16 );
        
        return openssl_decrypt( base64_decode( $string ), self::$method, $key, 0, $iv );
    }
}