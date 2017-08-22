<?php

namespace Modular\Traits;

use DOMNode;

trait mappable_map_delimited {
	public function mappableSourcePathDelimiter() {
		return '.';
	}

	/**
	 * Traverse the array data with a path like 'item.summary.title' in $data and return the value found at the end, if
	 * any.
	 *
	 * @param string  $path
	 * @param DOMNode $data
	 * @param bool    $found - set to true if found, false otherwise
	 *
	 * @return array|null|string
	 */
	public function traverse( $path, $data, &$found = false ) {
		$found = false;

		$segments = explode( $this->mappableSourcePathDelimiter(), $path );

		$pathLength = count( $segments );
		$parsed     = 0;

		while ( ! is_null( $segment = array_shift( $segments ) ) ) {
			$lastData = $data;

			if ( is_numeric( $segment ) ) {
				// array index
				if ( isset( $lastData[ $segment ] ) ) {
					$data = $lastData[ $segment ];
				}
				$found = true;
				break;

			} elseif ( isset( $data[ $segment ] ) ) {
				$data = $data[ $segment ];
				$parsed ++;

			} else {
				// failed to walk the full path, break out
				break;
			}
			$found = ( $parsed === $pathLength );
		}

		return $found ? $data : null;
	}

}