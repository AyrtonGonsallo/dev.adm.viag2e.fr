<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );

/**
 * SecuPress FPDF extended Class.
 *
 * @package SecuPress
 * @version 1.0.1
 * @since 1.0
 * @author Julio Potier
 */
class SecuPress_Pro_Admin_Scan_Report_PDF extends FPDF {

	const VERSION = '1.0.1';

	/**
	 * Print the header containing the title (WL), the URL and the date (filterable) + logo if not WL.
	 *
	 * @version 1.0
	 */
	public function Header() {
		$title = SECUPRESS_PLUGIN_NAME . ' ' . __( 'Site Health', 'secupress' );
		$this->SetFont( 'Arial', 'B', 15 );
		$this->SetFillColor( 34, 40, 65 );
		$this->SetTextColor( 43, 205, 193 );
		$this->Cell( 0, 10, $this->decode( $title ), 0, 1, 'C', 1 );

		$header = home_url() . ' - ' . date_i18n( _x( 'M jS, Y \a\t h:i:sa', 'pdf report date', 'secupress' ) );
		/**
		 * Filter the PDF report header.
		 *
		 * @since 1.0
		 *
		 * @param (string) $header The header text.
		 */
		$header = apply_filters( 'secupress.export_pdf.header', $header );
		$this->SetFont( 'Arial', 'I', 12 );
		$this->Cell( 0, 20, $this->decode( $header ), 'B', 0, 'C' );

		if ( ! secupress_is_white_label() ) {
			$this->Image( SECUPRESS_PATH . 'assets/admin/images/logo.png', 10, 6, 30 );
		}

		$this->Ln( 20 );
	}

	/**
	 * Print the grade subtext, the grade, the text (congratz etc) using max width.
	 *
	 * @since 2.0 use secupress_get_module_option( 'advanced-settings_grade-system', true )
	 * @since 1.0
	 */
	public function grade() {
		if ( ! secupress_get_module_option( 'advanced-settings_grade-system', true ) ) {
			return;
		}
		$subtext = secupress_get_scanner_counts( 'subtext' );
		$subtext = wp_strip_all_tags( str_replace( '—', '-', $subtext ) );
		$this->SetFont( 'Arial', '', 12 );
		$this->Cell( 0, 10, $this->decode( $subtext ), 0, 1, 'L' );

		$colors = explode( ',', secupress_get_scanner_counts( 'color' ) );
		$grade  = secupress_get_scanner_counts( 'grade' );
		$this->SetFont( 'Arial', 'B', 100 );
		$this->SetTextColor( $colors[0], $colors[1], $colors[2] );
		$this->Cell( 30, 30, $this->decode( $grade ), 0, 0, 'L' );

		$text = secupress_get_scanner_counts( 'text' );
		$text = $this->decode( $text );
		$size = 50;
		$w    = 158;

		while ( $w >= 158 ) {
			--$size;
			$this->SetFont( 'Arial', '', $size );
			$w = $this->GetStringWidth( $text );
		}

		$this->SetTextColor( 0 );
		$this->Cell( 0, 15, $text, 0, 1 );
		$this->Cell( 0, 15, '', 'B', 1 );
	}

	/**
	 * Print the home_url in footer (filterable).
	 *
	 * @version 1.0
	 */
	public function Footer() {
		$footer = home_url();
		/**
		 * Filter the PDF report footer.
		 *
		 * @since 1.0
		 *
		 * @param (string) $footer The footer text.
		 */
		$footer = apply_filters( 'secupress.export_pdf.footer', $footer );
		$this->SetY( -15 );
		$this->SetFont( 'Arial', 'I', 8 );
		$this->Cell( 0, 10, $this->decode( $footer ), 0, 0, 'C' );
	}

	/**
	 * Print all the module and their contents.
	 *
	 * @version 1.0
	 */
	public function print_modules() {
		$modules         = secupress_get_modules();
		$scanned_items   = secupress_get_scan_results();
		$scanned_items   = $scanned_items ? array_flip( array_keys( $scanned_items ) ) : array();
		$secupress_tests = secupress_get_scanners();
		$scanners        = secupress_get_scan_results();

		// Store the scans in 3 variables. They will be used to order the scans by status: 'bad', 'warning', 'good'.
		$bad_scans     = array();
		$warning_scans = array();
		$good_scans    = array();

		if ( ! empty( $scanners ) ) {
			foreach ( $scanners as $class_name_part => $details ) {
				if ( 'bad' === $details['status'] ) {
					$bad_scans[ $class_name_part ] = $details['status'];
				} elseif ( 'warning' === $details['status'] ) {
					$warning_scans[ $class_name_part ] = $details['status'];
				} elseif ( 'good' === $details['status'] ) {
					$good_scans[ $class_name_part ] = $details['status'];
				}
			}
		}

		secupress_require_class( 'scan', '' );

		foreach ( $secupress_tests as $module_name => $class_name_parts ) {
			$class_name_parts = array_combine( array_map( 'strtolower', $class_name_parts ), $class_name_parts );

			foreach ( $class_name_parts as $option_name => $class_name_part ) {
				if ( ! file_exists( secupress_class_path( 'scan', $class_name_part ) ) ) {
					unset( $class_name_parts[ $option_name ] );
					continue;
				}

				secupress_require_class( 'scan', $class_name_part );
			}

			$module_title   = ! empty( $modules[ $module_name ]['title'] )              ? $modules[ $module_name ]['title']              : '';
			$module_summary = ! empty( $modules[ $module_name ]['summaries']['small'] ) ? $modules[ $module_name ]['summaries']['small'] : '';

			$this->print_module_title( "$module_title - $module_summary" );

			if ( $scanned_items ) {
				// For this module, order the scans by status: 'good', 'warning', 'bad', 'new'.
				$this_module_good_scans    = array_intersect_key( $class_name_parts, $good_scans );
				$this_module_bad_scans     = array_intersect_key( $class_name_parts, $bad_scans );
				$this_module_warning_scans = array_intersect_key( $class_name_parts, $warning_scans );
				$class_name_parts          = array_merge( $this_module_good_scans, $this_module_warning_scans, $this_module_bad_scans );
				unset( $this_module_bad_scans, $this_module_warning_scans, $this_module_good_scans );
			}

			foreach ( $class_name_parts as $option_name => $class_name_part ) {
				$class_name   = 'SecuPress_Scan_' . $class_name_part;
				$current_test = $class_name::get_instance();

				// Scan.
				$scanner      = isset( $scanners[ $option_name ] ) ? $scanners[ $option_name ] : array();
				$scan_status  = ! empty( $scanner['status'] ) ? $scanner['status'] : 'notscannedyet';
				$scan_message = $current_test->title;

				if ( ! empty( $scanner['msgs'] ) ) {
					$scan_message = wp_strip_all_tags( secupress_format_message( $scanner['msgs'], $class_name_part ) );
				}

				$colors = $this->status_color( $scan_status );
				$status = secupress_status( $scan_status );

				$this->SetFont( 'Arial', '', 10 );
				$this->SetTextColor( 255 );
				$this->SetFillColor( $colors[0], $colors[1], $colors[2] );
				$this->Cell( 15, 8, $this->decode( $status ), 1, 0, 'C', true );

				$this->SetTextColor( 0 );
				$this->Cell( 0, 8, $this->decode( $scan_message ), 1, 1, 'L' );
			}
		}

		$this->Ln();
	}

	/**
	 * Helper to formate/decode text to add in the PDF.
	 *
	 * @version 1.0.1
	 *
	 * @param (string) $text Some text.
	 *
	 * @return (string)
	 */
	protected function decode( $text ) {
		static $exists;

		$text = html_entity_decode( $text );

		if ( ! isset( $exists ) ) {
			$exists = function_exists( 'iconv' );
		}

		if ( $exists ) {
			return iconv( 'UTF-8', 'windows-1252', $text );
		}

		return utf8_decode( $text );
	}

	/**
	 * Select the proper color depending of the status.
	 *
	 * @version 1.0
	 *
	 * @param (string) $status The input scan status.
	 *
	 * @return (array) 3 colors, RVB to match the actual CSS theme
	 */
	protected function status_color( $status ) {
		switch ( $status ) {
			case 'good'   : return array( 43,205,193 );
			case 'warning': return array( 247,171,19 );
			case 'bad'    : return array( 195,34,34 );
			default       : return array( 0,0,0 );
		}
	}

	/**
	 * Print the module title.
	 *
	 * @version 1.0
	 *
	 * @param (string) $label The string to be printed as a title.
	 */
	protected function print_module_title( $label ) {
		$this->Ln();
		$this->SetFont( 'Arial', '', 14 );
		$this->SetFillColor( 34, 40, 65 );
		$this->SetTextColor( 43, 205, 193 );
		$this->Cell( 0, 10, $this->decode( $label ), 0, 1, 'L', true );
	}
}
