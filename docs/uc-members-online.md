# Use Case: Member Organisations presented on website
## Situation
Many organisations that have organisations as their members, publish their member organisations on their website. Sometimes with limited data such as the name, country and website URL. Others publish full member information, with descriptions and whatever else they collect of their member organistaions.
This use case describes how to create the API with the Data Processor that a website can use to pick up the data from CiviCRM. 

## Requirements
* CiviCRM installed
* The Data Processor Extension installed (https://lab.civicrm.org/extensions/dataprocessor)
* The Data Processor Token Output extension installed (	https://lab.civicrm.org/extensions/dataprocessor-token-output)

## Setting up the Data Processor for Member publication
### Datasources
1. In CiviCRM go to Administer / DataProcessor / Add DataProcessor
1. Enter basic information on your Data Processor (Name, description)
![General Settings Data Processor](docs/images/dps_name_description.png)
1. Add Datasource "Organisation" - no need to add filters
1. Add datasource "Address" - leave default values for filters (primary address is 'yes' - Join type "Select fields to join on - not required" since we may not have address data of this organisation and in that case the organisation would not be shown at all. Join on field "Contact ID" and "Organisation :: Contact ID"
![Source Settings Address](docs/images/dp_source_settings.png)
1. Add Datasource "Website" - leave default values for filters - Joint type "Select fields to join on - not required" and Joint on field "Contact" and "Organisation :: contact ID"
![Source Settings Website](docs/images/dp_settings_website.png)
1. Add Datasource "Membership" - Filter on "Membership Status ID" "is one of" and then pick the statusses you want shown (current, new, pending) on the website - Select Join type should be set to "Select fields to join on" since the organisation must be a member in order to be shown and therefore there must be a membership and Join on Field "Contact ID" and "Organsisation:: Contact ID"
![Source Settings Website](docs/images/dp_settings_membership.png) 

Those were the required Datasources. 
### Fields
Now lets go to the fields that are necessary: Organisation Name, Organisation Country of primary address and Organisation Website. We'll also add the Organisation Contact ID so that we can also create an output Search. With it we can quickly check if the output API should work.
1. Add field "Contact ID" - Raw Field Value, i.e. what is written in this field in CiviCRM;
1. Add field "Name" - Raw field Value
1. Add field "Country" - Option Label (the label of the field, i.e. the country name here, rather than the country ID which would just be a number; and the Option from Option Label is because the field "Country" in CiviCRM is an option list, i.e. a list of all countries that you select the country from beloging to this address)
1. Add field "Website" - Raw Field Value
You should now have a list of fields like this:
![Source Settings Website](docs/images/dp_fields.png)

Those are the required fields. Since we don't need any sorting or more filters (we've done those on the datasources), we can now continue with the output. 
Note: if you want users on the website to be able to sort or filter, add filters here that can then be used by the webdeveloper through the API output we're creating in the next step. 

### Output (API)
The output we're creating is an API that can be used by the webdeveloper that is creating the page with the member organisations on it.
1. Go to the Output part of the Dataprocessor and choose "Add Output"
1. From the "Select output*" dropdown, select "API" (which will only be available if you have isntalled the The Data Processor Token Output extension  (	https://lab.civicrm.org/extensions/dataprocessor-token-output)
1. Enter a name in the API Entity field - keep that simple and do not use spaces in between characters; this is the name the webdeveloper will use to call the API;
1. leave the aPI Action Name and API GetCount Action Name with their default values
1. define the CiviCRM permission that is required to enable the use of this API; teh default value is perfect for this purpose.
1. Save the Data Processor Output
1. provide the API Enitity field value to the webdeveloper
![Source Settings Website](docs/images/DP_API_output.png)

Note: your webdeveloper will also need a Sitekey and API key in order to access CiviCRM data in the first place. Find out in CiviCRM manuals how to provide those.

### Output (Search)
To make sure that I have defined all properly, I usually also create a Search output so that I can check in CiviCRM that my dataprocessor does what I expect it to do. 
1. Add a second output, but this time select "Search/Report" from the Select output dropwdown. 
1. Add the Search form to a Parent menu in CiviCRM
1. Define the permission, the default is perfect. 
1. The ID field here would be the Contact ID
1. Define whether or not you want the ID field hidden
1. Save the form and look for the Search form under the menu you defined in step 2 here.

