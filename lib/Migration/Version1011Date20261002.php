<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the sharing tables:
 * - bbml_list_shares: per-list ACL between an owner and a guest
 * - bbml_catalog_shares: per-item catalog grants (guest → owner publishing)
 *
 * @psalm-suppress UnusedClass
 */
class Version1011Date20261002 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbml_list_shares')) {
			$table = $schema->createTable('bbml_list_shares');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('list_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('shared_with', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('mode', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'readonly']);
			$table->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'active']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['list_id'], 'bbml_lshare_list_idx');
			$table->addIndex(['shared_with'], 'bbml_lshare_user_idx');
			$table->addUniqueIndex(['list_id', 'shared_with'], 'bbml_lshare_uniq_idx');
		}

		if (!$schema->hasTable('bbml_catalog_shares')) {
			$table = $schema->createTable('bbml_catalog_shares');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('item_type', Types::STRING, ['length' => 16, 'notnull' => true]);
			$table->addColumn('item_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('shared_with', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'active']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['item_type', 'item_id'], 'bbml_cat_shr_item_idx');
			$table->addIndex(['shared_with'], 'bbml_cat_shr_user_idx');
			$table->addUniqueIndex(['item_type', 'item_id', 'shared_with'], 'bbml_cat_shr_uniq_idx');
		}

		return $schema;
	}
}
