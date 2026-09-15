<?php
/**
 * Public identity-erasure API.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ic_register_erasable_identity_source' ) ) {
	/**
	 * Registers an erasable identity source.
	 *
	 * @param string $key  Source key.
	 * @param array  $args Source configuration.
	 * @return bool
	 */
	function ic_register_erasable_identity_source( $key, $args ) {
		return IC_Identity_Erasure::register_source( $key, $args );
	}
}

if ( ! function_exists( 'ic_erasable_identity_sources' ) ) {
	/**
	 * Returns all erasable identity sources.
	 *
	 * @return array
	 */
	function ic_erasable_identity_sources() {
		return IC_Identity_Erasure::sources();
	}
}

if ( ! function_exists( 'ic_identity_removal_token' ) ) {
	/**
	 * Creates a stable-format removal token for a job.
	 *
	 * @param int $user_id User ID used by the fallback hash.
	 * @return string
	 */
	function ic_identity_removal_token( $user_id = 0 ) {
		$user_id = absint( $user_id );
		try {
			return bin2hex( random_bytes( 6 ) );
		} catch ( Exception $exception ) {
			return substr( wp_hash( $user_id . ':' . wp_generate_uuid4() . microtime( true ) ), 0, 12 );
		}
	}
}

if ( ! function_exists( 'ic_identity_erasure_blockers' ) ) {
	/**
	 * Returns active-entitlement blockers for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	function ic_identity_erasure_blockers( $user_id ) {
		return IC_Identity_Erasure::blockers( $user_id );
	}
}

if ( ! function_exists( 'ic_identity_erase_user' ) ) {
	/**
	 * Creates or reuses an identity-erasure job.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Job options.
	 * @return string|WP_Error
	 */
	function ic_identity_erase_user( $user_id, $args = array() ) {
		return IC_Identity_Erasure::create_job( $user_id, $args );
	}
}

if ( ! function_exists( 'ic_identity_erasure_step' ) ) {
	/**
	 * Advances one identity-erasure job.
	 *
	 * @param string $job_id Job ID.
	 * @param int    $limit  Batch limit.
	 * @return array|bool|WP_Error
	 */
	function ic_identity_erasure_step( $job_id, $limit = 0 ) {
		return IC_Identity_Erasure::step( $job_id, $limit );
	}
}

if ( ! function_exists( 'ic_identity_erasure_dispatch' ) ) {
	/**
	 * Dispatches queued identity-erasure work.
	 *
	 * @return bool
	 */
	function ic_identity_erasure_dispatch() {
		return IC_Identity_Erasure::dispatch();
	}
}

if ( ! function_exists( 'ic_identity_retention_sweep' ) ) {
	/**
	 * Runs the statutory identity retention sweep.
	 *
	 * @return int
	 */
	function ic_identity_retention_sweep() {
		return IC_Identity_Erasure::retention_sweep();
	}
}

if ( ! function_exists( 'ic_identity_record_is_severed' ) ) {
	/**
	 * Checks whether a record has been severed from its customer.
	 *
	 * @param int $record_id Record ID.
	 * @return bool
	 */
	function ic_identity_record_is_severed( $record_id ) {
		return 0 === strpos( (string) get_post_meta( absint( $record_id ), '_ic_removed_customer', true ), 'removed:' );
	}
}
