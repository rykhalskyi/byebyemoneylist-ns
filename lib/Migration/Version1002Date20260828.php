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
class Version1002Date20260828 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbml_list_items')) {
			$table = $schema->createTable('bbml_list_items');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('list_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('product_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('price', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => false]);
			$table->addColumn('quantity', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => true, 'default' => 1.0]);
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'added']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_list_items_owner_idx');
			$table->addIndex(['list_id'], 'bbml_list_items_list_idx');
			$table->addIndex(['product_id'], 'bbml_list_items_product_idx');
		}

		return $schema;
	}
}
