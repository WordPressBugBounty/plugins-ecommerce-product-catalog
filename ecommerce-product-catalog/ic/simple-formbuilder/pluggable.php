<?php
/**
 * Simple form builder pluggable helpers.
 *
 * @package impleCode\IC
 */

use WP2FA\Methods\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Defines pluggable functions.
 *
 * @version 1.0.0
 * @author  Norbert Dreszer
 */
if ( ! function_exists( 'get_all_products' ) ) {

	/**
	 * Returns all published product objects.
	 *
	 * @param array|null $args Optional post query args.
	 *
	 * @return array
	 */
	function get_all_products( $args = null ) {
		$args['post_type']      = product_post_type_array();
		$args['post_status']    = 'publish';
		$args['posts_per_page'] = - 1;
		$digital_products       = get_posts( $args );

		return $digital_products;
	}

}

if ( ! function_exists( 'ic_htmlize_email' ) ) {

	/**
	 * Initializes the HTML email template.
	 *
	 * @param string $message     Email message body.
	 * @param string $title       Email title.
	 * @param string $sender_name Sender display name.
	 * @param string $outside     Additional markup rendered after the email wrapper.
	 *
	 * @return string
	 * @global string $ic_mail_content
	 */
	function ic_htmlize_email( $message, $title, $sender_name, $outside = '' ) {
		$htmlized  = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>' . $title . '</title>
</head>
<body style="">
<style type="text/css">li {font-size:16px;line-height:1.5em;font-weight:bold}
	ul {width:80%;padding-left:30%} p{font-size: 16px;}';
		$htmlized .= apply_filters( 'ic_catalog_notification_styling', '' );
		$htmlized .= '
</style>
<div style="font-family: Verdana, sans-serif;color:#555555;font-size:16px;line-height:20px;background:#f5f5f5;width:100%;padding:25px 0 25px 0;margin:0;">';
		$htmlized .= '<div style="width:598px;max-width:100%;padding:0 0 10px 0;margin:0 auto 0 auto;">';
		$htmlized .= apply_filters( 'ic_catalog_notification_before_border_div', '' );
		$htmlized .= '
<div style="' . apply_filters( 'ic_catalog_notification_border_div_styling', 'background:#ffffff;width: 100%;border: 1px solid #cdcdcd;clear:both;' ) . '">';
		$htmlized .= apply_filters( 'ic_catalog_notification_before_top_div', '' );
		$htmlized .= '
<div style="' . apply_filters( 'ic_catalog_notification_top_div_styling', 'height:30px;clear:both;float:none;' ) . '">' . apply_filters( 'ic_catalog_notification_top_div_content', ' ' ) . '</div>
<div style="padding:15px 20px 20px 20px;margin: 0;font-size:16px;text-align:left;line-height:1.5em">' . str_replace( '<br>', '<br>' . "\n", $message ) . '</div>
<div style="height:30px;clear:both;float:none;"> </div>
		</div></div>';
		if ( ! is_email( $sender_name ) ) {
			/* translators: %s: sender site link. */
			$htmlized .= '<div style="text-align:center;line-height:1.5em;padding:5px;font-size:12px;color:#696969;width:100%;">' . sprintf( __( 'This email is a service from %s.', 'ecommerce-product-catalog' ), '<a href="' . site_url() . '" style="color:#696969">' . $sender_name . '</a>' ) . '</div>';
		}
		$htmlized .= '</div>' . $outside . '</body></html>';
		global $ic_mail_content;
		$ic_mail_content = $message;

		return $htmlized;
	}

}

if ( ! function_exists( 'ic_mail_alternate' ) ) {
	add_filter( 'phpmailer_init', 'ic_mail_alternate' );

	/**
	 * Adds a plain-text email alternative to the HTML message.
	 *
	 * @param object $mailer PHPMailer instance.
	 *
	 * @return object
	 * @global string $ic_mail_content
	 */
	function ic_mail_alternate( $mailer ) {
		global $ic_mail_content;
		if ( isset( $ic_mail_content ) && ! empty( $ic_mail_content ) ) {
			$table_tag = ic_email_table();
			if ( strpos( $ic_mail_content, $table_tag ) !== false ) {
				preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/s', $ic_mail_content, $matches );
				$rows = $matches[1];
				preg_match_all( '/<td[^>]*>(.*?)<\/td>/s', $rows[0], $matches );
				$th      = $matches[1];
				$replace = '';
				foreach ( $rows as $row_key => $row ) {
					if ( 0 !== $row_key ) {
						preg_match_all( '/<td[^>]*>(.*?)<\/td>/s', $row, $matches );
						$row_fields = $matches[1];
						foreach ( $row_fields as $key => $field ) {
							$replace .= $th[ $key ] . ': ' . $field . '<br>';
						}
						$replace .= '<br>';
					}
				}
				$ic_mail_content = preg_replace( '/<table[^>]*>(.*?)<\/table>/s', $replace, $ic_mail_content );
			}
			$button = addslashes( ic_email_button( '(.*?)' ) );
			if ( strpos( $button, $ic_mail_content ) !== false ) {
				$ic_mail_content = preg_replace( '/' . $button . '(.*?)<\/a>/i', '', $ic_mail_content );
			}
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer uses external property names.
			$mailer->AltBody = wp_strip_all_tags(
				str_replace(
					array( '<br>', '</p>', '<ul>' ),
					array(
						"\n",
						"\n",
						"\n",
					),
					$ic_mail_content
				)
			);
			// phpcs:enable
			$ic_mail_content = '';
			unset( $ic_mail_content );
		}

		return $mailer;
	}

}

if ( ! function_exists( 'ic_email_paragraph' ) ) {

	/**
	 * Initializes an HTML email paragraph.
	 *
	 * @param string|null $style Optional inline style string.
	 *
	 * @return string
	 */
	function ic_email_paragraph( $style = null ) {
		$p = '<p style="' . $style . '">';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_paragraph_end' ) ) {

	/**
	 * Initializes HTML email paragraph
	 *
	 * @return string
	 */
	function ic_email_paragraph_end() {
		$p = '</p>' . "\n";

		return $p;
	}

}

if ( ! function_exists( 'ic_email_ul' ) ) {

	/**
	 * Initializes HTML email ul
	 *
	 * @return string
	 */
	function ic_email_ul() {
		$p = '<ul style="width:70%;padding-left:10%">';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_ul_end' ) ) {

	/**
	 * Initializes HTML email ul
	 *
	 * @return string
	 */
	function ic_email_ul_end() {
		$p = '</ul>' . "\n";

		return $p;
	}

}

if ( ! function_exists( 'ic_email_li' ) ) {

	/**
	 * Initializes HTML email li
	 *
	 * @return string
	 */
	function ic_email_li() {
		$p = '<li style="font-size:16px;line-height:1.5em;font-weight:bold">';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_li_end' ) ) {

	/**
	 * Initializes HTML email li
	 *
	 * @return string
	 */
	function ic_email_li_end() {
		$p = '</li>' . "\n";

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table' ) ) {

	/**
	 * Initializes HTML email table
	 *
	 * @return string
	 */
	function ic_email_table() {
		$p = '<table cellspacing="0" cellpadding="10" border="0" style="margin: 15px 0;color:#555555;border: 1px solid #555555;width: 100%;">';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_end' ) ) {

	/**
	 * Finishes HTML email table
	 *
	 * @return string
	 */
	function ic_email_table_end() {
		$p = '</table>' . "\n";

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_tr' ) ) {

	/**
	 * Initializes HTML email tr
	 *
	 * @return string
	 */
	function ic_email_table_tr() {
		$p = '<tr>';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_tr_end' ) ) {

	/**
	 * Finishes HTML email tr
	 *
	 * @return string
	 */
	function ic_email_table_tr_end() {
		$p = '</tr>' . "\n";

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_th' ) ) {

	/**
	 * Initializes HTML email tr
	 *
	 * @return string
	 */
	function ic_email_table_th() {
		$p = '<tr style="font-weight: bold;">';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_th_end' ) ) {

	/**
	 * Finishes HTML email tr
	 *
	 * @return string
	 */
	function ic_email_table_th_end() {
		$p = '</tr>' . "\n";

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_td' ) ) {

	/**
	 * Initializes HTML email td
	 *
	 * @return string
	 */
	function ic_email_table_td() {
		$p = '<td style="text-align: center;">';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_td_first' ) ) {

	/**
	 * Initializes HTML email td
	 *
	 * @return string
	 */
	function ic_email_table_td_first() {
		$p = '<td>';

		return $p;
	}

}

if ( ! function_exists( 'ic_email_table_td_end' ) ) {

	/**
	 * Finishes HTML email td
	 *
	 * @return string
	 */
	function ic_email_table_td_end() {
		$p = '</td>' . "\n";

		return $p;
	}

}

if ( ! function_exists( 'ic_email_button' ) ) {

		/**
		 * Initializes an HTML email button.
		 *
		 * @param string $link Button URL.
		 *
		 * @return string
		 */
	function ic_email_button( $link ) {
		$a = '<a class="remove-plain" style="width:125px; display: block; font-size:15px; background-color:#bb0000;color:#ffffff; text-decoration:none; text-align:center; border-radius:10px; margin:10px auto; padding: 15px 10px 15px 10px;" target="_blank" href="' . $link . '">';

		return $a;
	}

}

if ( ! function_exists( 'ic_mail' ) ) {
	add_action( 'ic_mail_background_send', 'ic_mail', 10, 12 );
	/**
	 * Sends email using SMTP settings.
	 *
	 * @param string              $message           Email body.
	 * @param string              $sender_name       Sender display name.
	 * @param string              $sender_email      Sender email address.
	 * @param string              $receiver_email    Receiver email address.
	 * @param string              $title             Email subject.
	 * @param bool                $template          Whether HTML template should be applied.
	 * @param array               $attachments       Email attachments.
	 * @param array               $headers           Email headers.
	 * @param array|string        $smtp              SMTP settings array or callback name.
	 * @param IC_Imap|string|null $imap              IMAP handler instance or callback name.
	 * @param array|string|null   $reply_to          Reply-to email address.
	 * @param string              $previous_messages Previous thread messages.
	 *
	 * @return bool
	 */
	function ic_mail( $message, $sender_name, $sender_email, $receiver_email, $title, $template = true, $attachments = array(), $headers = array(), $smtp = '', $imap = null, $reply_to = null, $previous_messages = '' ) {
		if ( ic_is_message_spam( $sender_name, $sender_email, $message ) ) {
			return false;
		}

		if ( ! ic_email_domain_exists( $receiver_email ) ) {
			ic_error_log( 'IC MAIL: Email domain does not exist', false, true );

			return false;
		}

		if ( ! empty( $smtp ) ) {
			if ( current_filter() !== 'ic_mail_background_send' && current_filter() !== 'ic_mail_scheduled_background_send' && current_filter() !== 'ic_mail_scheduled_single_background_send' ) {
				ic_mail_schedule(
					$message,
					$sender_name,
					$sender_email,
					$receiver_email,
					$title,
					array(
						'template'          => $template,
						'attachments'       => $attachments,
						'headers'           => $headers,
						'smtp'              => $smtp,
						'imap'              => $imap,
						'reply_to'          => $reply_to,
						'previous_messages' => $previous_messages,
					)
				);

				return true;
			}
			if ( is_string( $smtp ) ) {
				$smtp = call_user_func( $smtp );
			}
			add_action(
				'phpmailer_init',
				function ( $phpmailer ) use ( $sender_email, $smtp, $imap, $headers ) {
					// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer uses external property names.
					$phpmailer->Mailer        = 'smtp';
					$phpmailer->Host          = $smtp['host'] ?? 'localhost';
					$phpmailer->SMTPAuth      = $smtp['auth'] ?? false;
					$phpmailer->Port          = $smtp['port'] ?? 25;
					$phpmailer->Username      = $smtp['username'] ?? '';
					$phpmailer->Password      = $smtp['password'] ?? '';
					$phpmailer->SMTPSecure    = $smtp['encryption'] ?? '';
					$phpmailer->From          = $sender_email;
					$phpmailer->Timeout       = 60;
					$phpmailer->SMTPDebug     = 0;
					$phpmailer->SMTPKeepAlive = true;
					$phpmailer->SMTPOptions   = array(
						'ssl' => array(
							'verify_peer'       => false,
							'verify_peer_name'  => false,
							'allow_self_signed' => true,
						),
					);
					foreach ( $headers as $key => $header ) {
						if ( strpos( $header, 'Message-ID' ) !== false ) {
							$message_id           = str_replace( array( 'Message-ID: ' ), '', $header );
							$phpmailer->MessageID = $message_id;
							unset( $headers[ $key ] );
						}
					}
					$phpmailer->addCustomHeader( 'X-Error-Handler', 'true' );
					$phpmailer->addCustomHeader( 'X-ImpleCode-System', 'true' );

					if ( ! empty( $imap ) ) {
						if ( is_string( $imap ) ) {
							$imap = call_user_func( $imap );
						}
						$phpmailer->action_function = function () use ( $imap, $phpmailer ) {
							try {
								if ( $imap ) {
									$imap->save_sent( $phpmailer->getSentMIMEMessage() );
								}
							} catch ( Exception $e ) {
								ic_error_log( 'Failed to save message to sent folder: ' . $e->getMessage(), false, true );
							}
						};
					}
					// phpcs:enable
				},
				20
			);
			foreach ( $headers as $key => $header ) {
				if ( strpos( $header, 'Message-ID' ) !== false ) {
					unset( $headers[ $key ] );
				}
			}
		}
		if ( ! empty( $sender_name ) ) {
			if ( is_email( $sender_name ) ) {
				$headers[] = 'From: ' . $sender_email . ' <' . $sender_email . '>';
				if ( empty( $reply_to ) ) {
					$headers[] = 'Reply-To: <' . $sender_name . '>';
				}
			} else {
				$headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
			}
		} else {
			$headers[] = 'From: ' . $sender_email . ' <' . $sender_email . '>';
		}
		if ( ! empty( $reply_to ) ) {
			$headers[] = 'Reply-To: <' . $reply_to . '>';
		}

		if ( ! empty( $previous_messages ) ) {
			if ( $template ) {
				$previous_messages = '<blockquote>' . $previous_messages . '</blockquote>';
			}
			$previous_messages = "\n\n" . str_repeat( '-', 40 ) . "\n\n" . $previous_messages;
		}
		if ( $template ) {
			add_filter( 'wp_mail_content_type', 'ic_mail_set_html' );
			$message = ic_htmlize_email( $message, $title, $sender_name, $previous_messages );
		} else {
			$message = wp_strip_all_tags(
				str_replace(
					array( '<br>', '</p>', '<ul>' ),
					array(
						"\r\n",
						"\r\n",
						"\r\n",
					),
					$message
				)
			);
			if ( ! empty( $previous_messages ) ) {
				$message .= $previous_messages;
			}
		}

		$result = wp_mail( $receiver_email, $title, $message, $headers, $attachments );
		remove_filter( 'wp_mail_content_type', 'ic_mail_set_html' );

		return $result;
	}
}
if ( ! function_exists( 'ic_mail_schedule' ) ) {

	/**
	 * Returns the background-send hook arguments for a scheduled mail item.
	 *
	 * @param string $message Email body.
	 * @param string $sender_name Sender display name.
	 * @param string $sender_email Sender email address.
	 * @param string $receiver_email Receiver email address.
	 * @param string $title Email subject.
	 * @param array  $settings Delivery settings.
	 *
	 * @return array
	 */
	function ic_mail_schedule_background_args( $message, $sender_name, $sender_email, $receiver_email, $title, $settings = array() ) {
		return array(
			$message,
			$sender_name,
			$sender_email,
			$receiver_email,
			$title,
			$settings['template'] ?? true,
			$settings['attachments'] ?? array(),
			$settings['headers'] ?? array(),
			$settings['smtp'] ?? '',
			$settings['imap'] ?? null,
			$settings['reply_to'] ?? null,
			$settings['previous_messages'] ?? '',
		);
	}

	/**
	 * Initializes the background mail queue database helper.
	 *
	 * @return object|false
	 */
	function ic_mail_schedule_db() {
		$override = apply_filters( 'ic_mail_schedule_db', null );
		if ( null !== $override ) {
			return $override;
		}
		if ( ! function_exists( 'ic_db_init' ) && file_exists( __DIR__ . '/../class-ic-db.php' ) ) {
			require_once __DIR__ . '/../class-ic-db.php';
		}
		if ( ! function_exists( 'ic_db_init' ) ) {
			return false;
		}

		return ic_db_init(
			'ic_mail_schedule',
			array(
				'sender_name'    => 'varchar(255)',
				'sender_email'   => 'varchar(254)',
				'receiver_email' => 'varchar(254)',
				'title'          => 'varchar(998)',
				'message'        => 'longtext',
				'settings'       => 'longtext',
				'status'         => "enum('pending','processing','sent','failed') DEFAULT 'pending'",
				'priority'       => 'tinyint UNSIGNED DEFAULT 5',
				'created_at'     => 'timestamp DEFAULT CURRENT_TIMESTAMP',
			),
			array( 'status', 'priority' )
		);
	}

	/**
	 * Schedules a direct background-send event.
	 *
	 * @param string $message Email body.
	 * @param string $sender_name Sender display name.
	 * @param string $sender_email Sender email address.
	 * @param string $receiver_email Receiver email address.
	 * @param string $title Email subject.
	 * @param array  $settings Delivery settings.
	 *
	 * @return void
	 */
	function ic_mail_schedule_fallback( $message, $sender_name, $sender_email, $receiver_email, $title, $settings = array() ) {
		wp_schedule_single_event(
			time() + 3,
			'ic_mail_background_send',
			ic_mail_schedule_background_args( $message, $sender_name, $sender_email, $receiver_email, $title, $settings )
		);
	}

	/**
	 * Schedule an email to be sent in the background.
	 *
	 * @param string $message        Email body.
	 * @param string $sender_name    Sender display name.
	 * @param string $sender_email   Sender email address.
	 * @param string $receiver_email Receiver email address.
	 * @param string $title          Email subject.
	 * @param array  $settings       Delivery settings.
	 *
	 * @return void
	 */
	function ic_mail_schedule( $message, $sender_name, $sender_email, $receiver_email, $title, $settings = array() ) {
		$db = ic_mail_schedule_db();
		if ( ! empty( $db ) && is_object( $db ) ) {
			if ( ! wp_next_scheduled( 'ic_mail_scheduled_background_send' ) ) {
				wp_schedule_event( time(), 'hourly', 'ic_mail_scheduled_background_send' );
			}
			$next_scheduled = wp_next_scheduled( 'ic_mail_scheduled_single_background_send' );
			if ( ! $next_scheduled || ( $next_scheduled - time() ) > 60 ) {
				wp_schedule_single_event( time() + 3, 'ic_mail_scheduled_single_background_send' );
			}
		}
		if ( empty( $db ) || ! is_object( $db ) ) {
			ic_mail_schedule_fallback( $message, $sender_name, $sender_email, $receiver_email, $title, $settings );
		} else {
			$inserted = $db->insert(
				array(
					'sender_name'    => $sender_name,
					'sender_email'   => $sender_email,
					'receiver_email' => $receiver_email,
					'title'          => $title,
					'message'        => $message,
					'settings'       => $settings,
				)
			);
			if ( false === $inserted ) {
				ic_mail_schedule_fallback( $message, $sender_name, $sender_email, $receiver_email, $title, $settings );
			}
		}
	}
}

if ( ! function_exists( 'ic_mail_scheduled_background_send' ) ) {
	add_action( 'ic_mail_scheduled_background_send', 'ic_mail_scheduled_background_send' );
	add_action( 'ic_mail_scheduled_single_background_send', 'ic_mail_scheduled_background_send' );

	/**
	 * Send scheduled background emails.
	 *
	 * @return void
	 */
	function ic_mail_scheduled_background_send() {
		if ( ! function_exists( 'ic_db_init' ) && file_exists( __DIR__ . '/../class-ic-db.php' ) ) {
			require_once __DIR__ . '/../class-ic-db.php';
		}
		if ( ! function_exists( 'ic_db_init' ) ) {
			return;
		}
		$db = ic_db_init( 'ic_mail_schedule' );
		if ( empty( $db ) || ! is_object( $db ) ) {
			return;
		}
		$scheduled_messages = $db->find(
			array(
				'status' => 'pending',
			)
		);
		if ( empty( $scheduled_messages ) ) {
			return;
		}
		$processed = 0;
		foreach ( $scheduled_messages as $message ) {
			if ( $processed >= 5 ) {
				break;
			}
			$db->update(
				$message['id'],
				array(
					'status' => 'processing',
				)
			);
			$settings = maybe_unserialize( $message['settings'] );
			ic_mail(
				$message['message'],
				$message['sender_name'],
				$message['sender_email'],
				$message['receiver_email'],
				$message['title'],
				$settings['template'] ?? true,
				$settings['attachments'] ?? array(),
				$settings['headers'] ?? array(),
				$settings['smtp'] ?? '',
				$settings['imap'] ?? null,
				$settings['reply_to'] ?? null,
				$settings['previous_messages'] ?? ''
			);
			$db->update(
				$message['id'],
				array(
					'status' => 'sent',
				)
			);
			++$processed;
		}
		if ( count( $scheduled_messages ) > $processed ) {
			$next_scheduled = wp_next_scheduled( 'ic_mail_scheduled_single_background_send' );
			if ( ! $next_scheduled || ( $next_scheduled - time() ) > 60 ) {
				wp_schedule_single_event( time() + 3, 'ic_mail_scheduled_single_background_send' );
			}
		}
	}
}
if ( ! function_exists( 'ic_email_domain_exists' ) ) {
	/**
	 * Check whether an email domain has valid DNS records.
	 *
	 * @param string $email Email address to validate.
	 *
	 * @return bool
	 */
	function ic_email_domain_exists( $email ) {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}
		$domain = substr( strrchr( $email, '@' ), 1 );

		if ( ! preg_match( '/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i', $domain ) ) {
			return false;
		}

		try {
			if ( checkdnsrr( $domain, 'MX' ) ) {
				return true;
			}

			if ( checkdnsrr( $domain, 'A' ) ) {
				return true;
			}

			return false;
		} catch ( Exception $e ) {
			return true;
		}
	}
}

if ( ! function_exists( 'ic_mail_set_html' ) ) {

	/**
	 * Set HTML content type for outgoing mail.
	 *
	 * @return string
	 */
	function ic_mail_set_html() {
		return apply_filters( 'ic_mail_html_content_type', 'text/html' );
	}

}

if ( ! function_exists( 'ic_is_message_spam' ) ) {

	/**
	 * Check whether a message matches the disallowed list.
	 *
	 * @param string $sender_name  Sender display name.
	 * @param string $sender_email Sender email address.
	 * @param string $message      Message body.
	 *
	 * @return bool
	 */
	function ic_is_message_spam( $sender_name, $sender_email, $message ) {
		$userip    = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:., ]/', '', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		$useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 254 ) : '';
		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $sender_name, $sender_email, '', $message, $userip, $useragent ) ) {
			return true;
		}
		if ( ! function_exists( 'wp_check_comment_disallowed_list' )
			// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_blacklist_checkFound -- Backward compatibility for older WordPress versions.
			&& wp_blacklist_check( $sender_name, $sender_email, '', $message, $userip, $useragent ) ) {
			return true;
		}

		return false;
	}

}

if ( ! function_exists( 'ic_container_shortcode' ) ) {
	add_shortcode( 'ic_container', 'ic_container_shortcode' );

	/**
	 * Render an opening container shortcode tag.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	function ic_container_shortcode( $atts ) {
		$available_args = array(
			'container' => 'span',
			'style'     => '',
		);
		$args           = shortcode_atts( $available_args, $atts );
		$style          = $args['style'];

		return '<' . $args['container'] . ' style="' . $style . '">';
	}

}

if ( ! function_exists( 'ic_container_shortcode_close' ) ) {
	add_shortcode( 'ic_container_close', 'ic_container_shortcode_close' );

	/**
	 * Render a closing container shortcode tag.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	function ic_container_shortcode_close( $atts ) {
		$available_args = array(
			'container' => 'span',
		);
		$args           = shortcode_atts( $available_args, $atts );

		return '</' . $args['container'] . '>';
	}

}
