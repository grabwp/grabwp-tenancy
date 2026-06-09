<?php
/**
 * GrabWP Tenancy Domain Validator
 *
 * Validates domain format and checks uniqueness across tenants.
 *
 * @package GrabWP_Tenancy
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Domain_Validator {

	/**
	 * Validate a list of domains, returning valid and invalid sets.
	 *
	 * @param array $domains Domains to validate.
	 * @return array ['valid' => [...], 'invalid' => [...]]
	 */
	public function validate_domains( $domains ) {
		$valid   = array();
		$invalid = array();

		foreach ( $domains as $domain ) {
			$domain = trim( $domain );
			if ( empty( $domain ) ) {
				continue;
			}

			if ( strlen( $domain ) > 253 ) {
				$invalid[] = substr( $domain, 0, 50 ) . '...';
				continue;
			}

			if ( ! $this->validate_format( $domain ) ) {
				$invalid[] = $domain;
				continue;
			}

			$valid[] = $domain;
		}

		return array(
			'valid'   => $valid,
			'invalid' => $invalid,
		);
	}

	/**
	 * Filter out placeholder domains (nodomain.local).
	 *
	 * @param array $domains Validated domains.
	 * @return array Real domains only.
	 */
	public function filter_real_domains( $domains ) {
		return array_filter(
			$domains,
			function ( $d ) {
				return 'nodomain.local' !== $d;
			}
		);
	}

	/**
	 * Validate a single domain format.
	 *
	 * @param string $domain Domain to validate.
	 * @return bool
	 */
	public function validate_format( $domain ) {
		if ( ! filter_var( $domain, FILTER_VALIDATE_DOMAIN ) ) {
			return false;
		}

		$domain = strtolower( trim( $domain ) );

		if ( ! preg_match( '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$/', $domain ) ) {
			return false;
		}

		$parts = explode( '.', $domain );
		if ( count( $parts ) < 2 ) {
			return false;
		}

		$tld = end( $parts );
		if ( strlen( $tld ) < 2 ) {
			return false;
		}

		$invalid_patterns = array(
			'/^[0-9]+$/',
			'/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/',
			'/^localhost$/',
			'/^127\.0\.0\.1$/',
		);

		foreach ( $invalid_patterns as $pattern ) {
			if ( preg_match( $pattern, $domain ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check domain uniqueness across all tenants.
	 *
	 * @param array  $domains           Domains to check.
	 * @param string $exclude_tenant_id Tenant to exclude (for updates).
	 * @return array ['unique' => bool, 'duplicates' => array]
	 */
	public function check_domain_uniqueness( $domains, $exclude_tenant_id = '' ) {
		$mappings_file   = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();
		$tenant_mappings = array();

		if ( file_exists( $mappings_file ) ) {
			include $mappings_file;
		}

		$duplicates           = array();
		$all_existing_domains = array();

		foreach ( $tenant_mappings as $tenant_id => $tenant_domains ) {
			if ( $exclude_tenant_id && $tenant_id === $exclude_tenant_id ) {
				continue;
			}
			if ( is_array( $tenant_domains ) ) {
				foreach ( $tenant_domains as $domain ) {
					$all_existing_domains[] = strtolower( trim( $domain ) );
				}
			}
		}

		foreach ( $domains as $domain ) {
			$domain_lower = strtolower( trim( $domain ) );
			if ( in_array( $domain_lower, $all_existing_domains ) ) {
				$duplicates[] = $domain;
			}
		}

		return array(
			'unique'     => empty( $duplicates ),
			'duplicates' => $duplicates,
		);
	}
}
