<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * @psalm-suppress UnusedClass
 */
class Version1005Date20260831 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$table = $schema->getTable('bbml_categories');
		if (!$table->hasColumn('status')) {
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'confirmed']);
		}

		return $schema;
	}
}
