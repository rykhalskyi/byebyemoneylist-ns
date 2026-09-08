<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Promote `address` and the store–category links to synced fields:
 * - add `bbml_stores.address` (nullable)
 * - create the store–category junction `bbml_store_categories`
 *
 * Mirrors `bbml_list_categories` from Version1006Date20260905.
 *
 * @psalm-suppress UnusedClass
 */
class Version1007Date20260907 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$stores = $schema->getTable('bbml_stores');
		if (!$stores->hasColumn('address')) {
			$stores->addColumn('address', Types::STRING, ['length' => 255, 'notnull' => false]);
		}

		if (!$schema->hasTable('bbml_store_categories')) {
			$table = $schema->createTable('bbml_store_categories');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('store_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('category_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['store_id'], 'bbml_store_categories_store_idx');
			$table->addIndex(['category_id'], 'bbml_store_categories_category_idx');
		}

		return $schema;
	}
}
