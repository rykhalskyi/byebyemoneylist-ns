<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * @psalm-suppress UnusedClass
 */
class Version1006Date20260905 extends SimpleMigrationStep {
	private IDBConnection $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$lists = $schema->getTable('bbml_lists');
		if (!$lists->hasColumn('position')) {
			$lists->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		}
		if (!$lists->hasColumn('purchase_date')) {
			$lists->addColumn('purchase_date', Types::DATETIME, ['notnull' => false]);
		}
		if (!$lists->hasColumn('is_finished')) {
			$lists->addColumn('is_finished', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}
		if (!$lists->hasColumn('updated_at')) {
			$lists->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);
		}

		$items = $schema->getTable('bbml_list_items');
		if (!$items->hasColumn('position')) {
			$items->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		}
		if (!$items->hasColumn('discount')) {
			$items->addColumn('discount', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		}
		if (!$items->hasColumn('custom_name')) {
			$items->addColumn('custom_name', Types::STRING, ['length' => 255, 'notnull' => false]);
		}
		if (!$items->hasColumn('updated_at')) {
			$items->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);
		}

		if (!$schema->hasTable('bbml_list_categories')) {
			$table = $schema->createTable('bbml_list_categories');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('list_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('category_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['list_id'], 'bbml_list_categories_list_idx');
			$table->addIndex(['category_id'], 'bbml_list_categories_category_idx');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->backfillTimestamps();
		$this->backfillFinishedFlag();
		$this->backfillListCategories();
	}

	private function backfillTimestamps(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_lists')
			->set('updated_at', $qb->createFunction('created_at'))
			->where($qb->expr()->isNull('updated_at'));
		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_list_items')
			->set('updated_at', $qb->createFunction('created_at'))
			->where($qb->expr()->isNull('updated_at'));
		$qb->executeStatement();
	}

	private function backfillFinishedFlag(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_lists')
			->set('is_finished', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq('status', $qb->createNamedParameter('finished', IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('is_finished', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		$qb->executeStatement();
	}

	private function backfillListCategories(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'category_id')
			->from('bbml_lists')
			->where($qb->expr()->isNotNull('category_id'));
		$result = $qb->executeQuery();

		/** @var list<array{id: string, category_id: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$insert = $this->db->getQueryBuilder();
		$insert->insert('bbml_list_categories')
			->values([
				'id' => $insert->createParameter('id'),
				'list_id' => $insert->createParameter('list_id'),
				'category_id' => $insert->createParameter('category_id'),
			]);

		foreach ($rows as $row) {
			if ($row['category_id'] === '') {
				continue;
			}
			$insert->setParameter('id', Uuid::v4(), IQueryBuilder::PARAM_STR);
			$insert->setParameter('list_id', $row['id'], IQueryBuilder::PARAM_STR);
			$insert->setParameter('category_id', $row['category_id'], IQueryBuilder::PARAM_STR);
			$insert->executeStatement();
		}
	}
}
