<?php

namespace Modular\Traits;

trait mappable_map_xml {
	/**
	 * Traverse the xml data with a path like '/item/summary/title' in $data and return the value found at the end, if
	 * any.
	 *
	 * @param string            $path
	 * @param \SimpleXMLElement $data
	 * @param bool              $found - set to true if found, false otherwise
	 *
	 * @return array|null|string
	 */
	public function traverse( $path, $data, &$found = false ) {
		$found = false;

		$data = $data->xpath( $path);

		$found = count($data);

		return $found ? $data : null;
	}

}
