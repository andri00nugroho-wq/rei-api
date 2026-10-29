-- Additive, rerunnable migration for the Branch API fields.
-- Existing values and rows are preserved; unknown status/coordinates stay NULL.
DELIMITER //

DROP PROCEDURE IF EXISTS `rei_add_branch_column_if_missing`//
CREATE PROCEDURE `rei_add_branch_column_if_missing`(
    IN p_column_name VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'branches'
          AND column_name = p_column_name
    ) THEN
        SET @branch_migration_sql = CONCAT(
            'ALTER TABLE `branches` ADD COLUMN `',
            REPLACE(p_column_name, '`', '``'),
            '` ',
            p_definition
        );
        PREPARE branch_stmt FROM @branch_migration_sql;
        EXECUTE branch_stmt;
        DEALLOCATE PREPARE branch_stmt;
    END IF;
END//

DELIMITER ;

CALL `rei_add_branch_column_if_missing`('latitude', 'DECIMAL(10,7) NULL');
CALL `rei_add_branch_column_if_missing`('longitude', 'DECIMAL(10,7) NULL');
CALL `rei_add_branch_column_if_missing`(
    'status',
    'ENUM(''active'', ''coming_soon'', ''temp_closed'', ''closed'') NULL DEFAULT NULL'
);
CALL `rei_add_branch_column_if_missing`('opening_date', 'DATE NULL');
CALL `rei_add_branch_column_if_missing`('city', 'VARCHAR(150) NULL');
CALL `rei_add_branch_column_if_missing`('province', 'VARCHAR(150) NULL');
CALL `rei_add_branch_column_if_missing`('district', 'VARCHAR(150) NULL');

DROP PROCEDURE `rei_add_branch_column_if_missing`;
