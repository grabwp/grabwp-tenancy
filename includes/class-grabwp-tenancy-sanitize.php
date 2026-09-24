<?php
/**
 * Shared slug sanitization — WordPress title-to-slug rules, hyphen-only charset.
 *
 * @package GrabWP_Tenancy
 * @since   1.1.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Sanitize {

	/**
	 * Convert a string to a public slug: lowercase [a-z0-9-], unicode stripped.
	 *
	 * Mirrors WordPress `sanitize_title()` / `remove_accents()` (ấ → a) then
	 * tightens the charset so underscores become hyphens.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function slug( $value ) {
		$value = (string) $value;

		if ( function_exists( 'remove_accents' ) ) {
			$value = remove_accents( $value );
		} else {
			$value = self::strip_accents( $value );
		}

		$value = strtolower( $value );
		$value = str_replace( array( '_', ' ', '.' ), '-', $value );
		$value = preg_replace( '/[^a-z0-9-]+/', '-', $value );
		$value = preg_replace( '/-+/', '-', $value );

		return trim( $value, '-' );
	}

	/**
	 * Whether a stored slug is valid [a-z0-9-] (hyphen-separated).
	 *
	 * @param mixed $value          Candidate slug.
	 * @param bool  $allow_underscore Accept legacy underscore separators.
	 * @return bool
	 */
	public static function is_valid_slug( $value, $allow_underscore = false ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}
		$pattern = $allow_underscore
			? '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'
			: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
		return (bool) preg_match( $pattern, $value );
	}

	/**
	 * Fallback accent stripping when WordPress is not loaded.
	 *
	 * @param string $value Input.
	 * @return string
	 */
	private static function strip_accents( $value ) {
		$map = array(
			'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
			'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
			'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
			'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
			'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
			'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
			'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
			'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
			'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
			'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
			'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
			'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
			'đ' => 'd',
			'À' => 'a', 'Á' => 'a', 'Ả' => 'a', 'Ã' => 'a', 'Ạ' => 'a',
			'Ă' => 'a', 'Ằ' => 'a', 'Ắ' => 'a', 'Ẳ' => 'a', 'Ẵ' => 'a', 'Ặ' => 'a',
			'Â' => 'a', 'Ầ' => 'a', 'Ấ' => 'a', 'Ẩ' => 'a', 'Ẫ' => 'a', 'Ậ' => 'a',
			'È' => 'e', 'É' => 'e', 'Ẻ' => 'e', 'Ẽ' => 'e', 'Ẹ' => 'e',
			'Ê' => 'e', 'Ề' => 'e', 'Ế' => 'e', 'Ể' => 'e', 'Ễ' => 'e', 'Ệ' => 'e',
			'Ì' => 'i', 'Í' => 'i', 'Ỉ' => 'i', 'Ĩ' => 'i', 'Ị' => 'i',
			'Ò' => 'o', 'Ó' => 'o', 'Ỏ' => 'o', 'Õ' => 'o', 'Ọ' => 'o',
			'Ô' => 'o', 'Ồ' => 'o', 'Ố' => 'o', 'Ổ' => 'o', 'Ỗ' => 'o', 'Ộ' => 'o',
			'Ơ' => 'o', 'Ờ' => 'o', 'Ớ' => 'o', 'Ở' => 'o', 'Ỡ' => 'o', 'Ợ' => 'o',
			'Ù' => 'u', 'Ú' => 'u', 'Ủ' => 'u', 'Ũ' => 'u', 'Ụ' => 'u',
			'Ư' => 'u', 'Ừ' => 'u', 'Ứ' => 'u', 'Ử' => 'u', 'Ữ' => 'u', 'Ự' => 'u',
			'Ỳ' => 'y', 'Ý' => 'y', 'Ỷ' => 'y', 'Ỹ' => 'y', 'Ỵ' => 'y',
			'Đ' => 'd',
		);
		$value = strtr( $value, $map );

		if ( class_exists( 'Normalizer' ) ) {
			$value = Normalizer::normalize( $value, Normalizer::FORM_D );
			$value = preg_replace( '/\p{Mn}+/u', '', $value );
		}

		return $value;
	}
}

/**
 * Convert a string to a hyphenated [a-z0-9-] slug.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function grabwp_sanitize_slug( $value ) {
	return GrabWP_Tenancy_Sanitize::slug( $value );
}
