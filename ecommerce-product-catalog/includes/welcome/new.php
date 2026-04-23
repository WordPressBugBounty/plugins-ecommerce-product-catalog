<?php
/**
 * Welcome screen new release content.
 *
 * @package ecommerce-product-catalog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

	<div class="about__section is-feature has-subtle-background-color">
		<h2>
			<?php
			printf(
			/* translators: %s: The current EPC version number. */
				esc_html__( 'Welcome to %s.', 'ecommerce-product-catalog' ),
				esc_html( IC_CATALOG_PLUGIN_NAME . ' ' . IC_CATALOG_VERSION )
			);
			?>
		</h2>
		<p>
			<?php esc_html_e( 'In this release cycle, your catalog gets more power in CTA, theme integration, speed and usability.', 'ecommerce-product-catalog' ); ?>
		</p>
	</div>

	<hr/>

	<div class="about__section has-1-column">
		<div class="column">
			<h2><?php esc_html_e( 'CTA Options', 'ecommerce-product-catalog' ); ?></h2>
			<p>
				<strong><?php esc_html_e( "CTA stands for call to action, and it's the part of a webpage that encourages the audience to do something.", 'ecommerce-product-catalog' ); ?></strong>
			</p>
			<p><?php esc_html_e( 'The aim of the 3.x releases is to make a variety of CTAs available so your catalog can convert your users to customers.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'Until now there are 3 CTAs available: Shopping Cart add to cart, Quote Cart add to cart and an affiliate button.', 'ecommerce-product-catalog' ); ?></p>
			<p>
				<?php
				/* Translators: 1: Opening contact link. 2: Closing contact link. */
				printf( __( 'If you have an idea of a great CTA that could be included, please %1$scontact the developers%2$s!', 'ecommerce-product-catalog' ), '<a target="_blank" href="' . esc_url( 'https://implecode.com/support/?support_type=different&cam=welcome&key=idea' ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
				?>
			</p>
		</div>
	</div>

	<div class="about__section has-1-column">
		<div class="column">
			<h2><?php esc_html_e( 'Theme integration', 'ecommerce-product-catalog' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'The theme integration is continuously being improved to support more themes and page builders.', 'ecommerce-product-catalog' ); ?></strong>
			</p>
			<p><?php esc_html_e( 'The aim of the 3.x releases is to make it compatible with 99% of the themes and page builders.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'In the current release, the theme integration goal is almost 100% complete.', 'ecommerce-product-catalog' ); ?></p>
			<p>
				<?php
				/* Translators: 1: Opening support forum link. 2: Closing support forum link. */
				printf( __( 'If you still face any theme or page builder integration issues, please report them on the %1$ssupport forum%2$s.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( 'https://wordpress.org/support/plugin/ecommerce-product-catalog/' ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
				?>
			</p>
			<p><?php esc_html_e( 'Thank you for all your feedback regarding theme integration issues!', 'ecommerce-product-catalog' ); ?></p>
		</div>
	</div>

	<div class="about__section has-1-column">
		<div class="column">
			<h2><?php esc_html_e( 'Speed', 'ecommerce-product-catalog' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Say hello to the fastest catalog experience.', 'ecommerce-product-catalog' ); ?></strong>
			</p>
			<p>
				<?php
				/* translators: %s: Plugin name. */
				printf( esc_html__( '%s is tested on websites with more than 40,000 products with many parameters, full-featured shopping cart and automatic product updates.', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
				?>
			</p>
			<p><?php esc_html_e( 'Thanks to many optimization tasks, your catalog pages and search results will load in less than a second.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'So your catalog can rank higher in the search engines and give the user the fastest experience possible.', 'ecommerce-product-catalog' ); ?></p>
		</div>
	</div>

	<div class="about__section has-1-column">
		<div class="column">
			<h2><?php esc_html_e( 'Usability', 'ecommerce-product-catalog' ); ?></h2>
			<p>
				<strong>
					<?php
					/* translators: %s: Plugin name. */
					printf( esc_html__( 'In this release, %s is continuously improved in the field of front-end and back-end usability', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
					?>
				</strong>
			</p>
			<p><?php esc_html_e( 'Small tweaks have been added in catalog design for the users.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'Most changes were added in the admin side to make the configuration easier for new users.', 'ecommerce-product-catalog' ); ?></p>
			<p>
				<?php
				/* Translators: 1: Opening support forum link. 2: Closing support forum link. */
				printf( __( 'If you face any configuration issues, please report it on the %1$ssupport forum%2$s.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( 'https://wordpress.org/support/plugin/ecommerce-product-catalog/' ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
				?>
			</p>
			<p><?php esc_html_e( 'Thanks to your feedback, we can make things easier together!', 'ecommerce-product-catalog' ); ?></p>
		</div>
	</div>

	<hr/>

	<div class="about__section has-2-columns has-accent-background-color is-wider-right">
		<div class="column">
			<h2><?php esc_html_e( 'Security, errors, feedback', 'ecommerce-product-catalog' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Frequent updates guarantee high security. The plugin is continuously being monitored for any security issues.', 'ecommerce-product-catalog' ); ?></strong>
			</p>
			<p>
				<?php
				/* Translators: 1: Opening support forum link. 2: Opening plugin website link. 3: Closing link tag. */
				printf( __( 'You can report any bugs or feedback on the %1$ssupport forum%3$s or on the %2$splugin website%3$s.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( 'https://wordpress.org/support/plugin/ecommerce-product-catalog/' ) . '">', '<a href="' . esc_url( 'https://implecode.com/support/?support_type=bug_report&cam=welcome&key=bug' ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
				?>
			</p>
			<p>
				<?php
				/* Translators: 1: Opening security report link. 2: Closing link. */
				printf( __( 'If you find any security issue, please report it %1$shere%2$s.', 'ecommerce-product-catalog' ), '<a href="' . esc_url( 'https://implecode.com/support/?support_type=bug_report&cam=welcome&key=security' ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
				?>
			</p>
			<p>
				<strong>
					<?php
					/* translators: %s: Plugin name. */
					printf( esc_html__( 'Thank you for all the effort that you put in testing and reporting. Without your involvement it would never be possible to make %s so reliable and secure!', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
					?>
				</strong>
			</p>
		</div>
		<div class="column about__image is-vertically-aligned-center">
			<figure aria-labelledby="about-security" class="about__image">
				<img src="<?php echo esc_url( AL_PLUGIN_BASE_PATH . 'img/implecode.png' ); ?>" alt="">
			</figure>
		</div>
	</div>

	<hr/>

	<div class="about__section has-2-columns has-subtle-background-color">
		<div class="column about__image is-vertically-aligned-center">
			<figure aria-labelledby="about-block-pattern" class="about__image">
				<img src="<?php echo esc_url( AL_PLUGIN_BASE_PATH . 'img/example-feedback.png' ); ?>" alt="">
			</figure>
		</div>
		<div class="column">
			<h2>
				<?php
				/* translators: %s: Plugin name. */
				printf( esc_html__( '%s reviews on WordPress.org', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
				?>
			</h2>
			<p><?php esc_html_e( 'Your reviews on WordPress.org help us to spread the word about this awesome catalog plugin.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'This is very important for the developers. We stay motivated and passionate in the sphere of continuous catalog development.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'Constructive feedback helps to take the right direction in the development.', 'ecommerce-product-catalog' ); ?></p>
			<p><a href="https://wordpress.org/support/plugin/ecommerce-product-catalog/reviews/#new-post"
					class="button-primary"
					target="_blank"><?php esc_html_e( 'Add your review', 'ecommerce-product-catalog' ); ?></a></p>
		</div>
	</div>

	<hr/>

	<div class="about__section has-1-column">
		<div class="column">
			<h2><?php esc_html_e( 'Documentation & Help', 'ecommerce-product-catalog' ); ?></h2>
			<p><?php esc_html_e( 'Now you can search through the catalog settings and docs from your admin dashboard!', 'ecommerce-product-catalog' ); ?></p>
			<p>
				<?php
				/* Translators: 1: Opening catalog settings link. 2: Opening help tab link. 3: Closing link. */
				printf( __( 'You can find the search box in the %1$scatalog settings%3$s or %2$shelp tab%3$s.', 'ecommerce-product-catalog' ), '<a target="_blank" href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=product-settings.php' ) ) . '">', '<a target="_blank" href="' . esc_url( admin_url( 'edit.php?post_type=al_product&page=extensions.php&tab=help' ) ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
				?>
			</p>
		</div>
	</div>

	<hr/>

	<div class="about__section has-subtle-background-color has-2-columns">
		<header class="is-section-header">
			<h2><?php esc_html_e( 'For developers', 'ecommerce-product-catalog' ); ?></h2>
				<p>
					<?php
					/* translators: %s: Plugin name. */
					printf( esc_html__( '%s is designed to make it easy for developers to customize things.', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME ) );
					?>
				</p>
		</header>
		<div class="column">
			<h3><?php esc_html_e( 'Theme integration', 'ecommerce-product-catalog' ); ?></h3>
			<p><?php esc_html_e( 'Even if the catalog works fine with any theme, you can take full control of the output.', 'ecommerce-product-catalog' ); ?></p>
			<p><a taget="_blank"
					href="https://implecode.com/wordpress/product-catalog/theme-integration-guide/#theme_integration&cam=welcome&key=theme-integration-guide"><?php esc_html_e( 'Check the advanced theme integration method', 'ecommerce-product-catalog' ); ?></a>
			</p>
		</div>
		<div class="column">
			<h3><?php esc_html_e( 'Template Customization', 'ecommerce-product-catalog' ); ?></h3>
			<p><?php esc_html_e( "You can customize the output by placing the template file in your theme 'implecode' folder.", 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'All the templates are located in the plugin templates folder.', 'ecommerce-product-catalog' ); ?></p>
			<p><a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/product-page-template/#cam=welcome&key=product-page-template"><?php esc_html_e( 'Check the details about template modification', 'ecommerce-product-catalog' ); ?></a>
			</p>
		</div>
	</div>

	<div class="about__section has-subtle-background-color has-2-columns">
		<div class="column">
			<h3><?php esc_html_e( 'Shortcodes', 'ecommerce-product-catalog' ); ?></h3>
			<p><?php esc_html_e( 'You can use many shortcodes to display the entire catalog or even each smallest part.', 'ecommerce-product-catalog' ); ?></p>
			<p><a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/product-catalog-shortcodes/#cam=welcome&key=product-catalog-shortcodes"><?php esc_html_e( 'Check all the shortcodes', 'ecommerce-product-catalog' ); ?></a>
			</p>
		</div>
		<div class="column">
			<h3><?php esc_html_e( 'CSS & PHP code snippets', 'ecommerce-product-catalog' ); ?></h3>
			<p><?php esc_html_e( 'We keep the list of most useful code snippets to adjust things.', 'ecommerce-product-catalog' ); ?></p>
			<p><a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/css-adjustments/#cam=welcome&key=css"><?php esc_html_e( 'CSS code snippets', 'ecommerce-product-catalog' ); ?></a>
				| <a target="_blank"
					href="https://implecode.com/docs/ecommerce-product-catalog/php-adjustments/#cam=welcome&key=php"><?php esc_html_e( 'PHP code snippets', 'ecommerce-product-catalog' ); ?></a>
			</p>
		</div>
	</div>

	<div class="about__section has-2-columns has-subtle-background-color is-wider-right">
		<div class="column">
			<h3><?php esc_html_e( 'Catalog Custom Coding', 'ecommerce-product-catalog' ); ?></h3>
			<p><?php esc_html_e( 'If you need a custom feature, do not hesitate to contact the developers.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'We know the plugin and WordPress to the ground, can adjust small things and create very complex features or integrations.', 'ecommerce-product-catalog' ); ?></p>
			<p><?php esc_html_e( 'We provide custom coding services in a professional and timely manner.', 'ecommerce-product-catalog' ); ?></p>
			<p><a href="https://implecode.com/support/?support_type=custom_job#cam=welcome&key=support"
					class="button-primary"
					target="_blank"><?php esc_html_e( 'Contact the developers', 'ecommerce-product-catalog' ); ?></a></p>
		</div>
		<div class="column about__image is-vertically-aligned-center">
			<figure aria-labelledby="about-block-pattern" class="about__image">
				<img src="<?php echo esc_url( AL_PLUGIN_BASE_PATH . 'img/example-customization-feedback.png' ); ?>" alt="">
			</figure>
		</div>
	</div>

	<hr class="is-small"/>

	<div class="about__section">
		<div class="column">
			<h3><?php esc_html_e( 'Check the documentation for more!', 'ecommerce-product-catalog' ); ?></h3>
			<p>
				<?php
				/* Translators: 1: Current EPC version number. 2: Opening documentation link. 3: Closing link. */
				printf( __( 'There’s a lot more for developers to love in %1$s. To discover more and learn how to make the catalog shine on your sites, themes, plugins and more, check the %2$sdocumentation.%3$s', 'ecommerce-product-catalog' ), esc_html( IC_CATALOG_PLUGIN_NAME . ' ' . IC_CATALOG_VERSION ), '<a href="' . esc_url( 'https://implecode.com/docs/#cam=welcome&key=docs' ) . '">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
				?>
			</p>
		</div>
	</div>
