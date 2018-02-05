<?php

	$checkFalsy = function( $value, $ctx ) {
		$isFalsy = (empty( $value ) || ctype_space( $value ));
		
		$errors = array_key_exists( 'errors', $ctx )
			?	$ctx[ 'errors' ]
			:	[];
		
		if ($isFalsy) {
			$message = $ctx[ 'type' ] . ' can\'t be empty.';
			
			array_push( $errors, $message );
		};
			
		$ctx['errors'] = $errors;
		
		return $ctx;
	};

	$checkFormat = function( $format ) {
		return function( $value, $ctx ) use ( $format ) {
			$formats = [
				"email" => "/([\w\.\-_]+)?\w+@[\w-_]+(\.\w+){1,}/",
				"zipcode" => "/^(?:0[1-9]|[1-9]\d)\d{3}$/",
				"birthday" => "/^(0[1-9]|1[0-2])[\/](0[1-9]|[12]\d|3[01])[\/](19|20)\d{2}$/",
				"default" => "//",
			];

			$regex = array_key_exists( $format, $formats )
				?	$formats[ $format ]
				:	$formats[ "default" ];

			$passes = preg_match( $regex, $value );
		
			$errors = array_key_exists( 'errors', $ctx )
				?	$ctx[ 'errors' ]
				:	[];

			if (! $passes) {
				$message = $ctx[ 'type' ] . ' isn\'t formatted correctly.';

				array_push( $errors, $message );
			};

			$ctx[ 'errors' ] = $errors;

			return $ctx;
		};
	};

	$fetch = function( $url, $timeout ) {
        $conn = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => 1,
        ];

        curl_setopt_array( $conn, $options );
		
		$response = curl_exec( $conn );
		
		curl_close( $conn );
		
		return json_decode( $response, true );
	};

	$validateZipcode = function( $value, $ctx ) use ( $fetch ) {
		$api_key = '9TkV38CqACwjavaWuxqarmadAcLCZMPtqBadD8uitljSNM5OiGHzwRV1eHlE98BY';
		$passes = true;
		
        $response = $fetch(
            'https://www.zipcodeapi.com/rest/' . $api_key . '/distance.json/37912/' . $value . '/miles',
            5
        );
        $distance = $response
            ?   $response[ 'distance' ]
            :   false;

        $ctx[ 'response' ] = $response;
		
		$errors = array_key_exists( 'errors', $ctx )
			?	$ctx[ 'errors' ]
			:	[];

		if (! $distance) {
            $message = array_key_exists( 'error_msg', $response )
                ?   $response[ 'error_msg' ]
                :   $ctx[ 'value' ] . ' isn\'t a valid zip.';

			array_push( $errors, $message );
		};

		$ctx[ 'errors' ] = $errors;

		return $ctx;	
	};

	$dateGreaterThan = function( $duration, $unit ) {
		return function( $value, $ctx ) use ( $duration, $unit ) {
			$date = strtotime( $value );
			$difference = time() - $date;
			
			$convert = [
				"years" => function( $d ) {
					return $d / (60 * 60 * 24 * 364);
				},
				"months" => function( $d ) {
					return $d / (60 * 60 * 24 * 12);
				},
				"weeks" => function( $d ) {
					return $d / (60 * 60 * 24 * 52);
				},
				"days" => function( $d ) {
					return $d / (60 * 60 * 24);
				},
				"hours" => function( $d ) {
					return $d / (60 * 60);
				},
				"minutes" => function( $d ) {
					return $d / (60);
				},
				"seconds" => function ( $d ) {
					return $d;
				}
			];
			
			$passes = $convert[ $unit ]( $difference ) > $duration;
			
			$errors = array_key_exists( 'errors', $ctx )
				?	$ctx[ 'errors' ]
				:	[];

			if (! $passes ) {
				$message = $ctx[ 'type' ] . ' isn\'t greater than ' . $duration . ' ' . $unit . '.';

				array_push( $errors, $message );
			};

			$ctx[ 'errors' ] = $errors;

			return $ctx;
		};
	};

	$validator = [
		"email" => [
			$checkFalsy,
			$checkFormat( 'email' ),
		],
		"first" => [
			$checkFalsy,
		],
		"last" => [
			$checkFalsy
		],
		"zip" => [
			$checkFalsy,
			$checkFormat( 'zipcode' ),
			$validateZipcode,
		],
		"birthday" => [
			$checkFalsy,
			$checkFormat( 'birthday' ),
			$dateGreaterThan( 21, 'years' ),
		]
	];
    
    function validateForm( $type, $value ) {

		global $validator;
        
        $funcs = array_key_exists( $type, $validator )
			?	$validator[ $type ]
			:	[];
        
        $ctx = [
			'type' => $type,
			'value' => $value,
		];
        
        for ( $f = 0; $f < sizeOf( $funcs ); $f++ ) {
            $func = $funcs[ $f ];
            
            $ctx = $func( $value, $ctx );
        }

        $errors = array_key_exists( 'errors', $ctx )
				?	$ctx[ 'errors' ]
                :	[];

        $passes = sizeOf( $errors ) === 0;
                
		$ctx[ 'passes' ] = $passes ? 'true' : 'false';
        
        return $passes;
	};