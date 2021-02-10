# Data Flow Classes

A Data Flow Class determines how the data flows through the data processor. Most of the time this would be an SQL Query.

The following data flow classes are available:

* `InMemoryDataFlow` - Use this to have the data in memory. E.g. when your data comes from a CSV file you can use this
  data flow to hold all data.
* `CsvDataFlow` - Data flow for loading data from a CSV file.
* `CombinedDataFlow` - Combine multiple data flows into one.
* `SqlTableDataFlow` - Use this data flow to retrieve data from a database table.
* `CombinedSqlDataFlow` - Use this data flow to create an sql query with a join on multiple tables.
* `SubqueryDataFlow` - Use this data flow to create a query based on a sub query.
* `UnionQueryDataFlow` - Use this data flow to create a sub query with an Union statement.

Abstract classes for data flow:

* `AbstractDataFlow` - This is the base class for the data flow. All Data Flows should inherit this class.
* `SqlDataFlow` - This is the base class for all the SQL based data flows.

## Data Flow Classes: CombinedDataFlow, CombinedSqlDataFlow and SubQueryDataFlow

When you use a class which combines multiple data flows. Such as the `CombinedSqlDataFlow` then you add the other data flows by
calling `addSourceDataFlow` which expects a `DataFlowDescription` class. The `DataFlowDescription` describes the data flow and
how the data flow should be joined to the other data flows.

## Data Flow Classes: UnionQueryDataFlow

This class generates a query like

```sql
    SELECT id, display_name FROM civicrm_contact WHERE contact_type = 'Individual'
    UNION
    SELECT id, display_name FROM civicrm_contact WHERE contact_type = 'Household'
```

When you use the `UnionQueryDataFlow` class you can add additional query by calling the method `addSourceDataFlow`. This
method expects a `DataFlowDescription` which will then hold the `DataFlow` for the next select statement.
Each added data flow should hold the same number of columns in the same order.

