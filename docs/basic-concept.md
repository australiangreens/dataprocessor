# Basic Concept of the Data Processor Extension

The **Data Processor** extension allows you to select data sources, select what fields you want to use from those datasources, how they should be filtered and sorted, and what kind of output you will then create.

For example:
* I want a list of all the active members with their country and website, and publish this on my public website.
* I want to create a report with some actions
* I want to export some data to a CSV file

In this chapter we will briefly discuss the forms and screens you need to go through if you are creating a **Data Processor**.

## Menu option Data Processor

Once you installed the **Data Processor** extension you will have an additional option in the **Administer** menu called **Data Processor**. This menu will have two options, **Manage Data Processors** and **Add Data Processor**. The first will give you view of all the data processors in your installation. The second will allow you to create a new data processor.

## Manage Data Processors Page

Initially the **Manage Data Processors** will be empty when you have just installed the extension and look like this:

![Manage Data Processors](docs/images/manage_dps.png)

You can add a new data processor or import a data processor. The latter can be useful if you tried to create one in your test environment and then want to use it on your production environment. With export and import you can easily move data processors between environments.

## Add Data Processor Form
You can create a new data processor by selecting the menu option **Add Data Processor** (Administer>Data Processor>Add Data Processor) or by clicking the button **Add DataProcessor** on the **Manage Data Processors** page. If you do this you will see a form like the one below. On  this form you can add the _title_ and _description_ for the data processor and then hit **Next** to save and continue.

![Add Data Processor](docs/images/add_dp.png)

When you have entered the title and description and hit next you will be presented with a form like this:

![New Data Processor Form](docs/images/dp_first.png)

On this form you have four elements that you can add: **Data Sources**, **Fields**, **Filters** and **Output**.
You can check what these elements do in detail in the **How to** examples in this documentation, below is a quick overview.

### Data Sources

!!! Note "You need to build your knowledge of the CiviCRM database"
    To be able to use the data processor, especially when joining data sources, will require some detailed knowledge on how the CiviCRM database works.
    Not in very technical detail, but you would for example need to know that memberships are in a separate table and that they are joined with the _contact_id_.

Literally the source(s) where your data is coming from. This can be known CiviCRM entities like _Contact_ or _Activity_ but can also be a CSV file or an SQL table that you created yourself. When selecting your data source you can specify filters that will be used when selecting the data from these sources.

!!! Note "For developers...."
    It is possible to create your own data source if you want to. More information in the section [Add Your Own Data Source](add_your_own_datasource.md)

For example, I select the data source _Contact_ but only want to see Individuals. I would then filter on _Contact Type is one of Individual_.

![Filter on contact type Individual](docs/images/dp_data_source_filter.png)

If you use more than one data source you will have to specify how they are joined. For example, if I add the entity _Activity_ to my data processor that already has the entity _Contact_. I will then have to specify how contact and activity are joined so that I only get the activities for the relevant contacts.

When specifying how data sources are joined I will be presented with this form:

![Select Join Type](docs/images/dp_join1.png)

 You can see that there are 2 types of joins:
 1. Select fields to join on
 1. Select fields to join on (not required)

The first option will when joining ONLY select those records where both data sources have data. The second option will always select records from the _first_ data source (where you are joining _from_) and will either show data from the _second_ data source or leave those fields empty.

An example to illustrate:
* I have selected the entities Contact and Membership in that sequence (so Contact is my first)
* The data looks like this:

![Data for Join Example](docs/images/join_example.png)

* If I use the first type of join my data processor will only show contacts Martha Hamster and Bor de Wolf. Only in those cases do I have data in both of my data sources.
* If I use the second type of join my data processor will show all contacts but the membership fields will be empty for Ed Bever.

Once I have selected the type of join I can specify what fields I have to join on. In my example that is the Contact ID from the Membership has to be the same as the Contact ID of the Contact, see:

![Join Example](docs/images/dp_join2.png)

## Fields
Once you have selected your data source(s) you can then select what fields you want to use from those data sources.
