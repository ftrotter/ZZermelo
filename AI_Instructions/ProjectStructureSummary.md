# Zermelo Project Structure Summary

This document provides a comprehensive overview of the Zermelo reporting engine architecture, core workflows, and relationship with the LoreCommander test project.

---

## Project Overview

**Zermelo** is a PHP/Laravel reporting engine that transforms SQL SELECT statements into interactive web-based reports. The core philosophy is "SQL-first" - report authors think exclusively in SQL, and Zermelo handles all the complexity of web rendering, pagination, filtering, sorting, and caching.

### Key Characteristics

- **SQL-First Design**: Reports are defined by SQL queries, not visual builders
- **Cache-as-Table**: Query results are cached as real MariaDB tables
- **Multiple Output Formats**: Tabular (DataTables), Cards (Bootstrap), Graphs (D3), TreeCards
- **Laravel Integration**: Works as a Laravel package with service providers and artisan commands

---

## Directory Structure

```
Zermelo/
├── AI_Instructions/           # AI assistant guidance documents
├── documentation/             # User-facing documentation
│   ├── Architecture.md
│   ├── Card.README.md
│   ├── Graph.README.md
│   ├── Tabular.README.md
│   ├── TreeCard.README.md
│   ├── ConfigFile.md
│   └── ControlCaching.md
├── src/                       # Core source code
│   ├── Console/               # Artisan commands
│   ├── Exceptions/            # Custom exception classes
│   ├── Http/
│   │   ├── Controllers/       # API and web controllers
│   │   └── Requests/          # Request handling
│   ├── Interfaces/            # PHP interfaces
│   ├── Models/                # Core business logic
│   │   ├── ZermeloReport.php  # Base report class
│   │   ├── DatabaseCache.php  # Caching engine
│   │   └── AbstractGenerator.php
│   ├── Reports/               # Report type implementations
│   │   ├── Tabular/
│   │   ├── Cards/
│   │   ├── Graph/
│   │   └── Tree/
│   └── Services/              # Service classes
├── composer.json              # Package dependencies
└── README.md                  # Main documentation
```

---

## Core Workflow

### 1. Report Definition

Reports are PHP classes that extend abstract report types:

```php
class MyReport extends AbstractTabularReport {
    public function GetReportName(): string { return "My Report"; }
    public function GetReportDescription(): ?string { return "Description"; }
    public function GetSQL() {
        return "SELECT * FROM my_table WHERE status = 'active'";
    }
}
```

### 2. Request Flow

```
HTTP Request
    │
    ▼
TabularApiController::index()
    │
    ▼
ZermeloRequest::buildReport()
    │ Creates report instance with:
    │ - Code (first URL segment after report name)
    │ - Parameters (subsequent URL segments)
    │ - Input (GET/POST parameters)
    │
    ▼
new DatabaseCache(report)
    │ Checks cache validity:
    │ - Does cache table exist?
    │ - Is caching enabled?
    │ - Is cache expired?
    │ - Was clear_cache requested?
    │
    ▼ (if cache invalid)
createTable()
    │ - DROP existing cache table
    │ - Execute report SQL
    │ - Store results in cache table
    │ - Run index commands
    │
    ▼
ReportGenerator::toJson()
    │ - Apply filters against cache table
    │ - Apply sorting against cache table
    │ - Paginate results
    │ - Transform rows via MapRow()
    │ - Return JSON response
    │
    ▼
Frontend (DataTables/Cards/Graph)
```

---

## Caching System (Critical Component)

The caching system is central to Zermelo's performance and functionality.

### Location

- **File**: `src/Models/DatabaseCache.php`
- **Cache Database**: Configured as `_zermelo_cache` by default

### How Caching Works

1. **Cache Table Naming**
   - Uses `getDataIdentityKey()` from the report
   - Formula: `ReportClassName_MD5(className + code + sql_statements)`
   - Same report with different SQL = different cache table

2. **Cache Invalidation Triggers**
   Any of these conditions regenerate the cache:
   - Cache table doesn't exist
   - `isCacheEnabled()` returns false (default)
   - User requests `clear_cache=true`
   - Cache is expired (based on `howLongToCacheInSeconds()`)

3. **Cache Table Creation** (`createTable()`)
   ```php
   // First SELECT creates the table
   CREATE TABLE cache_table 
   ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
   AS SELECT ...

   // Subsequent SELECTs insert into existing table
   INSERT INTO cache_table SELECT ...

   // Non-SELECT statements (UPDATE, etc.) execute directly
   ```

4. **Multi-Query Support** (`getIndividualQueries()`)
   - Reports can return single SQL string OR array of strings
   - Semicolon-separated queries are split into individuals
   - All SELECTs must have matching column counts
   - Results are combined into single cache table

### Cache Configuration in Reports

```php
class MyReport extends AbstractTabularReport {
    // Enable/disable caching
    public function isCacheEnabled() {
        return true;  // Default is false
    }
    
    // How long before cache expires
    public function howLongToCacheInSeconds() {
        return 1200;  // 20 minutes
    }
    
    // Optional: Custom indexes for the cache table
    public function GetIndexSQL(): ?array {
        return [
            "ALTER TABLE {{_CACHE_TABLE_}} ADD INDEX(`column_name`);",
            "ALTER TABLE {{_CACHE_TABLE_}} ADD PRIMARY KEY(`id`);"
        ];
    }
}
```

---

## Report Types

### 1. Tabular Reports (`AbstractTabularReport`)

- **URL**: `/Zermelo/ReportName/`
- **Output**: DataTables-powered interactive table
- **Features**: Sorting, filtering, pagination, column formatting
- **Key File**: `src/Reports/Tabular/AbstractTabularReport.php`

```php
class ExampleTabular extends AbstractTabularReport {
    public function GetSQL() { return "SELECT id, name, amount FROM sales"; }
    
    public function MapRow(array $row, int $row_number): array {
        // Decorate rows with HTML
        $row['name'] = "<a href='/details/{$row['id']}'>{$row['name']}</a>";
        return $row;
    }
    
    public function OverrideHeader(array &$format, array &$tags): void {
        $format['amount'] = 'CURRENCY';
        $tags['id'] = ['HIDDEN'];
    }
}
```

### 2. Card Reports (`AbstractCardsReport`)

- **URL**: `/ZermeloCard/ReportName/`
- **Output**: Bootstrap card grid layout
- **Key File**: `src/Reports/Cards/AbstractCardsReport.php`

Special SQL column aliases:
- `card_header`, `card_title`, `card_text`
- `card_img_top`, `card_img_bottom`
- `card_footer`, `card_body`
- `card_layout_block_id`, `card_layout_block_label` (for grouping)

### 3. Graph Reports (`AbstractGraphReport`)

- **URL**: `/ZermeloGraph/ReportName/`
- **Output**: D3.js force-directed graph
- **Key File**: `src/Reports/Graph/AbstractGraphReport.php`

Required SQL columns for nodes and edges:
```sql
SELECT 
    source_id, source_name, source_size, source_type, source_group,
    source_latitude, source_longitude, source_img, source_json_url,
    target_id, target_name, target_size, target_type, target_group,
    target_latitude, target_longitude, target_img, target_json_url,
    weight, link_type, query_num
FROM relationships
```

### 4. TreeCard Reports (`AbstractTreeReport`)

- **URL**: `/ZermeloTreeCard/ReportName/`
- **Output**: Hierarchical tree structure with cards
- **Key File**: `src/Reports/Tree/AbstractTreeReport.php`

Required SQL columns:
- `root`, `root_url`
- `branch`, `branch_url`
- `leaf`, `leaf_url`

---

## Key Functions in Report Classes

### Required Functions

| Function | Purpose |
|----------|---------|
| `GetReportName()` | Return report title |
| `GetReportDescription()` | Return description (HTML allowed) |
| `GetSQL()` | Return SQL string or array of SQL strings |

### Optional Functions

| Function | Purpose |
|----------|---------|
| `MapRow($row, $row_num)` | Transform each row for display |
| `OverrideHeader(&$format, &$tags)` | Set column formats and tags |
| `GetIndexSQL()` | Return SQL for indexing cache table |
| `isCacheEnabled()` | Enable/disable caching |
| `howLongToCacheInSeconds()` | Cache expiration time |
| `GetReportFooter()` | Footer HTML |
| `GetReportJS()` | Custom JavaScript |
| `isSQLPrintEnabled()` | Enable SQL debug view |

### Input/Parameter Access

```php
// Inside GetSQL() or other methods:
$this->getCode();           // First URL segment after report name
$this->getParameters();     // Array of subsequent URL segments
$this->getInput('key');     // GET/POST parameter
$this->getNumericCode();    // Code only if numeric
$this->setInput('key', $val);  // Override input
$this->setDefaultSortOrder([['col' => 'asc']]);  // Default sorting
$this->quote($userInput);   // SQL escape for user input
```

---

## Column Formatting

### Auto-Detection

Columns are auto-formatted based on name patterns:

| Format | Matched Names |
|--------|--------------|
| `CURRENCY` | Amt, Amount, Paid, Cost |
| `NUMBER` | id, #, Num, Sum, Total, Cnt, Count |
| `DECIMAL` | Avg, Average |
| `PERCENT` | Percent, Ratio, Percentage |
| `URL` | URL |
| `DETAIL` | Sentence |

### Header Tags

```php
$tags['column_name'] = ['BOLD'];    // Bold column header
$tags['column_name'] = ['HIDDEN'];  // Hide column by default
$tags['column_name'] = ['ITALIC'];  // Italic column header
$tags['column_name'] = ['RIGHT'];   // Right-align column
```

---

## LoreCommander (Test Project)

**LoreCommander** is a Laravel project that uses Zermelo to report on Magic: The Gathering card data from Scryfall.

### Purpose

- Exercises Zermelo with real and test reports
- Validates that Zermelo continues to function correctly
- Provides examples of all report types

### Key Files

- **Report Location**: `LoreCommander/app/Reports/`
- **Example Reports** (non-DURC_):
  - `CardSearch.php` - Tabular report with search form
  - `BinderReport.php` - Card-based binder layout
  - `RegGraph.php` - Graph visualization

### DURC Reports

Files prefixed with `DURC_` are auto-generated by the DURC (Database Utility Report Creator) sister project. DURC generates Zermelo reports for each table in a database. For Zermelo development, these should be treated as "just more reports" - Zermelo must function independently of DURC.

---

## API Endpoints

| Endpoint | Purpose |
|----------|---------|
| `/Zermelo/{report}` | Tabular report web view |
| `/ZermeloCard/{report}` | Card report web view |
| `/ZermeloGraph/{report}` | Graph report web view |
| `/ZermeloTreeCard/{report}` | TreeCard web view |
| `/api/Zermelo/{report}` | Tabular JSON API |
| `/api/Zermelo/{report}/Summary` | Summary statistics |
| `/api/Zermelo/{report}/Download` | CSV/Excel download |
| `/ZermeloSQL/{report}` | SQL debug view (if enabled) |

---

## Dependencies (from composer.json)

- **PHP**: >= 7.2.0
- **Laravel**: 5.5+ (via service provider)
- **Frontend**:
  - Bootstrap 4.1.3
  - jQuery 3.3.1
  - DataTables 1.10.21
  - Font Awesome 5.15.2
  - Moment.js 2.29.1
- **Backend**:
  - doctrine/sql-formatter 1.1.*
  - phpoffice/phpspreadsheet ^1.22

---

## Configuration

Config file: `config/zermelo.php`

Key settings:
- `REPORT_NAMESPACE` - Where to find report classes (default: `App\Reports`)
- `API_PREFIX` - API URL prefix (default: `zapi`)
- `TABULAR_API_PREFIX` - Tabular URL prefix (default: `Zermelo`)
- `ZERMELO_CACHE_DB` - Cache database name
- `MIDDLEWARE` - Additional middleware for routes

---

## Common Patterns

### Creating a Linked Report

```php
public function MapRow(array $row, int $row_number): array {
    $id = $row['id'];
    $name = $row['name'];
    $row['name'] = "<a href='/Zermelo/DetailReport/$id'>$name</a>";
    return $row;
}
```

### Dynamic SQL Based on Input

```php
public function GetSQL() {
    $status = $this->getInput('status') ?? 'active';
    $status = $this->quote($status);  // SQL injection protection
    return "SELECT * FROM items WHERE status = $status";
}
```

### Report with Search Form

```php
public function GetReportDescription(): ?string {
    return "
    <form method='GET'>
        <input name='search' type='text' placeholder='Search...'>
        <button type='submit'>Search</button>
    </form>";
}
```

---

## Summary

Zermelo's power comes from:

1. **SQL expertise is rewarded** - Complex queries become rich reports without additional coding
2. **Cache tables are real tables** - Enables efficient filtering, sorting, pagination
3. **MapRow for decoration** - Raw data preserved for analysis, decoration at display time
4. **Multiple output formats** - Same SQL can power tables, cards, or graphs
5. **Laravel integration** - Uses familiar patterns, blade templates, middleware

For specific implementation questions, refer to the source code in `src/` and example reports in `LoreCommander/app/Reports/`.
