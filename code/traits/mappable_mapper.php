<?php

namespace Modular\Traits;

use DataObject;
use Modular\Exceptions\Mappable as Exception;
use Modular\Interfaces\Mappable as MappableInterface;
use Modular\Interfaces\Mappable;
use ValidationException;

trait mappable_mapper {

	private $sourceName;

	/**
	 * @param int $bitfield   with some bits set
	 * @param int $bitsToTest check these bits are 1 in the bitfield
	 *
	 * @return bool true if all bits set in bitsToTest are set in bitfield
	 */
	abstract public function testbits( $bitfield, $bitsToTest );

	/** @return DataObject|MappableInterface */
	abstract public function model();

	/**
	 * @param string $path  delimited path in data to traverse, e.g. 'item.contents.chunks[1]'
	 * @param mixed  $data e.g. array from json or DOMNode/DOMDocument for XML
	 * @param bool   $found set to true path was found in data, otherwise false
	 *
	 * @return
	 */
	abstract public function traverse( $path, $data, &$found = false );

	/**
	 * Given data in a nested array, a field map to a flat structure and a DataObject to set field values
	 * on populate the model.
	 *
	 * @param string|MappableInterface $sourceName source to get map from config.mappable_map for the model
	 * @param array|string             $data
	 * @param int                      $options    bitfield of or'd self::OptionXYZ flags
	 *
	 * @return int - number of fields found for mapping
	 * @throws \ValidationException
	 * @throws null
	 */
	public function mappableUpdate( $sourceName, $data, $options = MappableInterface::DefaultMappableOptions ) {
		$model = $this->model();

		if ( ! $map = $model->mappableMapForSource( $sourceName ) ) {
			throw new Exception( "No map found for endpoint '$sourceName'" );
		}
		$this->sourceName = $sourceName;

		$numFieldsFound = 0;

		foreach ( $map as $fieldInfo ) {
			$found = false;

			// data path is the first value in tuple
			$dataPath = $fieldInfo[0];

			$value = static::traverse( $dataPath, $data, $found );

			if ( $found ) {
				$this->found( $value, $fieldInfo, $options );
				$numFieldsFound ++;
			} else {
				$this->notFound( $fieldInfo, $options );
			}
		}

		return $numFieldsFound;
	}

	/**
	 * A value was found so map it to the DataObject.
	 *
	 * @param  mixed $value
	 * @param  array $fieldInfo
	 * @param  int   $options bitfield of or'd self::OptionXYZ flags
	 *
	 * @return bool|null
	 * @throws Exception
	 * @throws ValidationException
	 */
	protected function found( $value, $fieldInfo, $options = MappableInterface::DefaultMappableOptions ) {
		/** $var \DataObject|MappableInterface $model */
		$model = $this->model();

		list( , $modelPath, , $isTagField, $method, $relationshipName ) = $fieldInfo;

		$result = null;

		if ( $method ) {

			$this->mapMethod( $method, $value, $model, $fieldInfo, $options, $result );

		} elseif ( $model->hasMethod( MappableInterface::CustomMapMethodPrefix . $relationshipName ) ) {

			$this->mapMethod( MappableInterface::CustomMapMethodPrefix . $relationshipName, $value, $model, $fieldInfo, $options, $result );

		} elseif ( $model->hasMethod( MappableInterface::CustomMapMethodPrefix . $modelPath ) ) {

			$this->mapMethod( MappableInterface::CustomMapMethodPrefix . $modelPath, $value, $model, $fieldInfo, $options, $result );

		} elseif ( is_array( $value ) ) {
			// map an incoming array to the model
			$result = $this->mapArray( $value, $model, $fieldInfo, $options );

		} else {
			// map a single value to the model
			$result = $this->mapSingleValue( $value, $model, $fieldInfo, $options );
		}

		return $result;
	}

	protected function mapSingleValue( $value, DataObject $model, array $fieldInfo, $options ) {
		$delimiter = static::path_delimiter();

		list( , $modelPath, , $isTagField, $method, $relationshipName ) = $fieldInfo;

		$mapped = false;

		// map single value
		if ( $relationshipName ) {
			// handle one-to-one relationship with a lookup field (could be the id or another field, e.g a 'Code' field).
			list( $relationshipName, $lookupFieldName ) = explode( $delimiter, $relationshipName );

			if ( $relatedClass = $model->hasOne( $relationshipName ) ) {
				/** @var DataObject $relatedModel */
				$relatedModel = $relatedClass::get()->filter( $lookupFieldName, $value )->first();

				if ( $relatedModel ) {
					$idField = $relationshipName . 'ID';

					$model->{$idField} = $relatedModel->ID;

					// TODO validate this works, as in what if there are more than one?
					$backRelationships = $relatedModel->hasMany();
					array_map(
						function ( $relationshipName, $className ) use ( $model, $relatedModel ) {
							if ( $className == $model->class ) {
								$relatedModel->$relationshipName()->add( $model );
							}
						},
						array_keys( $backRelationships ),
						array_values( $backRelationships )
					);
				}
			}
		} elseif ( $model->hasField( $modelPath ) ) {

			$model->$modelPath = $value;
			$mapped            = true;
		}

		return $mapped;
	}

	protected function mapArray( array $value, DataObject $model, array $fieldInfo, $options ) {
		$mapped = false;

		list( , $modelPath, , $isTagField, $method, $relationshipName ) = $fieldInfo;
		// map array to relationships or single field
		if ( $isTagField && ! self::testbits( $options, MappableInterface::OptionSkipRelationships ) ) {
			$relationshipName = $modelPath;

			// add foreign keys ('Tags') creating the foreign record if necessary and options say so

			if ( ! $foreignClass = $model->hasManyComponent( $relationshipName, true ) ) {
				if ( $manyMany = $model->manyManyComponent( $relationshipName, true ) ) {
					if ( isset( $manyMany[1] ) ) {
						$foreignClass = $manyMany[1];
					}
				}
			}

			if ( $foreignClass ) {
				foreach ( $value as $foreignKey ) {
					$foreignKeyField = $fieldInfo[1];

					$related = $foreignClass::get()->Filter( [
						$foreignKeyField => $foreignKey,
					] );
					if ( ! $related && self::testbits( $options, MappableInterface::OptionCreateRelatedModels ) ) {
						$related = new $foreignClass( [
							$foreignKeyField => $foreignKey,
						] );
					}
					if ( $related ) {
						$model->$relationshipName()->add( $related );
						$mapped = true;
					}
				}
			} elseif ( $model->hasField( $modelPath ) ) {
				$model->$modelPath = implode( MappableInterface::DefaultTagDelimiter, $value );
				$mapped            = true;
			}

		} elseif ( ! self::testbits( $options, MappableInterface::OptionShallow ) ) {

			if ( $relatedClass = $model->hasManyComponent( $relationshipName ) ) {
				// add has_many related objects as new objects

				if ( $this->testbits( $options, MappableInterface::OptionDeleteOneToMany ) ) {
					/** @var DataObject $related */
					foreach ( $model->$relationshipName() as $related ) {
						$related->delete();
					}
				}
				if ( $this->testbits( $options, MappableInterface::OptionClearOneToMany ) ) {
					// remove related records first
					$model->$relationshipName()->removeAll();
				}
				foreach ( $value as $foreignData ) {
					// add a new foreign model to this one.

					/** @var DataObject|MappableInterface $foreignModel */
					$foreignModel = new $relatedClass();
					$foreignModel->mappableUpdate( $this->sourceName, $foreignData, $options );
					$foreignModel->write( true );

					$model->$relationshipName()->add( $foreignModel );
					$mapped = true;
				}
			}
		}

		return $mapped;
	}

	/**
	 * @param string      $method
	 * @param mixed       $value
	 * @param \DataObject $model
	 * @param array       $fieldInfo
	 * @param mixed       $options
	 * @param mixed       $result
	 *
	 * @return bool true if method exists (and so was called), false otherwise.
	 */
	protected function mapMethod( $method, $value, DataObject $model, array $fieldInfo, $options, &$result ) {
		if ( $hasMethod = $model->hasMethod( $method ) ) {
			$result = $model->$method( $value, $fieldInfo );
		}

		return $hasMethod;
	}

	protected function notFound( $fieldInfo, $options ) {
		$model = $this->model();

		if ( ! $this->testbits( $options, MappableInterface::OptionSkipNulls ) ) {

			list( , $modelPath, $foreignKey ) = $fieldInfo;

			if ( $foreignKey && $this->testbits( $options, MappableInterface::OptionRemoveObsoleteRelationships ) ) {
				if ( $className = $model->hasOneComponent( $modelPath ) ) {
					// TODO: remove foreign key relationships

				} elseif ( $className = $model->hasMany( $modelPath ) ) {
					// TODO: remove foreign key relationships

				} elseif ( list( , $className ) = $model->manyManyComponent( $modelPath ) ) {

					// TODO: remove foreign key relationships

				}
			} else {
				$model->$modelPath = null;
			}
		}
	}

}
