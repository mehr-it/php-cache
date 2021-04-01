<?php /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */


	namespace MehrIt\PhpCache\Concerns;


	trait DataEntries
	{
		/**
		 * Sets the given value in the data array
		 * @param array $data The data array
		 * @param string $key The key
		 * @param mixed $value The value
		 * @param int $expiresAt The expiration timestamp
		 */
		protected function setValue(array &$data, string $key, $value, int $expiresAt): void {

			$requiresSerialization = $this->requiresSerialization($value);

			if (($expiresAt && $expiresAt <= time()) || $value === null) {
				// delete item, if already expired (requested by PSR)
				
				unset($data[$key]);
			}
			else {
				$data[$key] = [
					$expiresAt,
					$requiresSerialization,
					$requiresSerialization ? serialize($value) : $value,
				];
			}
			
		}

		/**
		 * Removes the value for the given field in the data array
		 * @param array $data The data array
		 * @param string $key The key
		 */
		protected function removeValue(array &$data, string $key): void {

			unset($data[$key]);
		}

		/**
		 * Gets the value for the given key in the data array
		 * @param array $data The data array
		 * @param string $key The key
		 * @param bool|null $hit Returns true if was hit. Else false.
		 * @param bool|null $shouldClean Returns if obsolete data for the key should be cleaned
		 * @return mixed|null The value
		 */
		protected function getValue(array &$data, string $key, bool &$hit = null, bool &$shouldClean = null) {

			$entry = $data[$key] ?? null;

			if (is_array($entry)) {
				[$expiresAt, $isSerialized, $value] = $entry;

				// check expiration
				if (!$expiresAt || $expiresAt > time()) {

					$hit         = true;
					$shouldClean = false;

					return $isSerialized ?
						unserialize($value) :
						$value;
				}
			}


			$hit         = false;
			$shouldClean = ($entry !== null);

			return null;
		}

		/**
		 * Checks if a value exists for the given key in the data array
		 * @param array $data The data array
		 * @param string $key The key
		 * @return mixed|null The value
		 */
		protected function hasValue(array &$data, string $key): bool {

			$entry = $data[$key] ?? null;
			if (is_array($entry)) {

				$expiresAt = $entry[0];

				// check expiration
				if (!$expiresAt || $expiresAt > time())
					return true;

			}

			return false;
		}

		/**
		 * Checks if a value must be serialized.
		 * @param mixed $var The value
		 * @return bool True if to serialize. False if simple var_export is sufficient
		 */
		protected function requiresSerialization(&$var): bool {

			// walk arrays
			if (is_array($var)) {
				foreach ($var as &$curr) {
					if ($this->requiresSerialization($curr))
						return true;
				}
			}

			// Objects need serialization. Other variables not.
			return is_object($var);

		}
	}