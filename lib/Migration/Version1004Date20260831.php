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
class Version1004Date20260831 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$table = $schema->getTable('bbml_lists');
		if (!$table->hasColumn('is_subscription')) {
			$table->addColumn('is_subscription', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}
		if (!$table->hasColumn('is_income')) {
			$table->addColumn('is_income', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}
		if (!$table->hasColumn('is_recurring')) {
			$table->addColumn('is_recurring', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}
		if (!$table->hasColumn('recurring_period')) {
			$table->addColumn('recurring_period', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'MONTH']);
		}
		if (!$table->hasColumn('is_forward_empty')) {
			$table->addColumn('is_forward_empty', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
		}

		return $schema;
	}
}
