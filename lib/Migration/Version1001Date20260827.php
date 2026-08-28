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
class Version1001Date20260827 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbml_products')) {
			$table = $schema->createTable('bbml_products');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('barcode', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('category_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'reviewed']);
			$table->addColumn('picture_path', Types::STRING, ['length' => 500, 'notnull' => false]);
			$table->addColumn('is_subscription', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('is_favorite', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('is_income', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_products_owner_idx');
			$table->addIndex(['category_id'], 'bbml_products_category_idx');
		}

		if (!$schema->hasTable('bbml_product_aliases')) {
			$table = $schema->createTable('bbml_product_aliases');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('product_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('alias_name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('store_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_product_aliases_owner_idx');
			$table->addIndex(['product_id'], 'bbml_product_aliases_product_idx');
		}

		return $schema;
	}
}
