# Version 1.11.0 (not yet released)

* Added Field Output Handler for outputting a text when a contact has a certain relationship.
* Refactored Field Output Handlers and simplified duplicate code for initializing a field.

# Version 1.10.0

* Added option to return URL to File Download Link Field.

# Version 1.9.1

* Fixed regression issue with MySQL function such as Year on the Date Field.

# Version 1.9.0

* Fixed issue with filtering on contact subtype.
* Fixed issue with returning after a participant task.
* Added data source for membership payments.
* Added Contact Checksum Field.
* Added header fields to the PDF export output
* Fixed bug in File Download Link Field.

# Version 1.8.0

* Added Manage Case Link field.
* Added checkbox to show Manage Case on the Case Search output.
* Fixed issue with dashlet opening in full screen.

# Version 1.7.1

* Fixed issue with cloning data processors.

# Version 1.7.0

* Fixed #35: Custom Fields on a tab are also available as field now.
* Changed Age field so aggeragation is working correctly.
* Changed Field Specification to allow more advanced mysql functions.
* Added Event Filter.
* Added Formatted Address field.
* Added data source for note
* Refactored API Output to an Abstract Class so that it is easy for extension developers to develop their own implementation.
* Added Markup/Html Field Value output field handler.
* Improved In Memory Dataflow so that joins and filters would work.
* Improved Contact Summary Tab output so it includes a count.
* Fixed caching issues on the contact data source #31.
* Fixed bugs with ContactInGroup filter #33

# Version 1.6.0

* Update to avoid using a system function that is being deprecated. (See !37)
* Fixed issue with case role field.

# Version 1.5.0

* Added relationship type order by Relationship Field Type.
* Added smart group contact data source.

# Version 1.4.0

* Search tasks (eg. Export) work with Member,Contribute,Participant,Case...
* Added source to retrieve the owner membership, when owner membership is not set (meaning it is already the primary) then it will return itself.
* Added date filter to filter date with the PHP Date Format.
* Added filtering on Contact (sub) type on the contact filter.
* Added PDF Export Output
* Added Union Query Data Flow.
* Added a field specification for a fixed value
* Fixed #24
* Improved export/import functionality.
* Added documentation generator to the API output.
* Added default sort configuration for a data processor (#26).
* Added Age field.
* Added current user to contact filter.
* Added data source for permissioned contact (#25).
* Fixed issue with configuration contact source sub type filter.
* Added a no result text to the outputs.

# Version 1.3.0

* Fixed the dashlets.
* Fixed caching issues.
* Add Recurring Contribution as datasource
* Added Field Output Handler for Is Active fields based on dates (start date and end date).
* Refactored the factory (the factory is used by developers to add data source, field outputs, outputs, filters etc.).
* Added data sources for custom groups which are a multiple data set.

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
