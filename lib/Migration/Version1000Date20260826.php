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
class Version1000Date20260826 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbml_lists')) {
			$table = $schema->createTable('bbml_lists');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('store_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('category_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'new']);
			$table->addColumn('final_total', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_lists_owner_idx');
		}

		if (!$schema->hasTable('bbml_stores')) {
			$table = $schema->createTable('bbml_stores');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_stores_owner_idx');
		}

		if (!$schema->hasTable('bbml_categories')) {
			$table = $schema->createTable('bbml_categories');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('color', Types::STRING, ['length' => 7, 'notnull' => false]);
			$table->addColumn('emoji', Types::STRING, ['length' => 8, 'notnull' => false]);
			$table->addColumn('parent_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('income', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_categories_owner_idx');
		}

		return $schema;
	}
}
