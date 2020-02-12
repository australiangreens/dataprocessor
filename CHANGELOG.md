# Version 1.3.0 (not yet released)

* Fixed the dashlets.
* Fixed caching issues.

# Version 1.2.0

* Made CSV Export download available for anonymous users.
* Change Group Filter so that it also works with smart groups
* Fixed bug with date filter
* Added date group by function to date output field handler.
* Added exposure of Aggregation on the Search/Report output.

**Remark for extension developers**

If you have an extension which implements an `OutputHandlerAggregate` in your _Field Output Handlers_ then you
have to implement to additional methods: `enableAggregation` and `disableAggregation`.

# Version 1.1.0

* Respect selected permissions for outputs
* Allow to specify "Is Empty" for various filters.
* Allow to limit ContactFilter to only show contacts from specific groups.
* Output a data processor as a dashboard.
* Output a data processor as a tab on the contact summary screen.
* Output a data processor as a contribution search.
* Output a data processor as a membership search.
* Added field outputs for simple calculations (substract and total).
* Added escaped output to search screens.
* Replaced the value separator in the raw field with a comma.
* Added filter to search text in multiple fields.
* Added filter for searching contacts with a certain tag.
* Added filter for searching contacts with a certain type.
* Added filter for contact has membership.
* Added filter to respect the ACL. So that a user only sees the contacts he is allowed to see.
* Removed the title attribute from the outputs as those don't make sense.
* Refactored aggregation functionality and added aggregation function field.
* Fixed issue with updating navigation after editing an output.
* Added option to expand criteria forms on search forms.
* Added a Date field.
* Added function to clone a data processor.
* Added Case ID field on the activity source.
* Added field to display relationships.
* Added is not empty as a filter operator.
* Added hidden fields option to search outputs, dashboard output and contact summary tab output.
* Added formatted number output field handler
* Added SQL Table Data Source
* Export from a search only exports the selected rows.

# Version 1.0.7

* Changed Event Participants Field Output Handler to return a string.
* Build a cache clear when a data processor configuration is changed.

# Version 1.0.6

* Performance improvement by caching the data processor and the api calls.

# Version 1.0.5

* Added error handling to importer
* Added sort in Manage data processor screen

# Version 1.0.4

* Fixed issue with activity search and actions after the search when the actions are run on all records.

# Version 1.0.3

* Fixed issue with date filters.

# Version 1.0.2

* Fixed bug #11 (Fatal error clone on non object)

# Version 1.0.1

Initial release.