<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add receipt_path column to bbml_lists to store optional attached receipt image references.
 *
 * @psalm-suppress UnusedClass
 */
class Version1010Date20260914 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('bbml_lists')) {
			$table = $schema->getTable('bbml_lists');
			if (!$table->hasColumn('receipt_path')) {
				$table->addColumn('receipt_path', Types::STRING, ['length' => 255, 'notnull' => false]);
			}
		}

		return $schema;
	}
}
