# Use Case: Create tokens that can be used in mail sent from CiviCRM
## Situation
When sending mail from CiviCRM, in bulk or individual - manual or automated, organisations usually want to personalise the content of mail (Dear John rather than Dear Sir/Madam) or add specific data from CiviCRM so the mail. This use case is about the need to send an automated appointment reminder to contacts, a day before the appointment. The reminder should contain date and time, with who and where the appointment is.

Note: by default, CiviCRM has a number of default tokens available. There are also a number of CiviCRM extensions that provide extra tokens. Check those out since they might also provide you with what you need. The advantage here is that you can define any tokens yourself, so that you'll have the non-default tokens all in one place, rather than some default, some from extensions and some from the Data Processor.

## Requirements
* CiviCRM installed
* The Data Processor Extension installed (https://lab.civicrm.org/extensions/dataprocessor)
* The Data Processor Token Output extension installed (	https://lab.civicrm.org/extensions/dataprocessor-token-output)
* Activity type "Appointment" (or any other type you want to use for this goal)

## Setting up the Data Processor extra tokens
### Datasources

1. In CiviCRM go to Administer / DataProcessor / Add DataProcessor
1. Enter basic information on your Data Processor (Name, description) Note that the Name you give this dataprocessor will be the title of the tokenset such as it appears to users when writing their mail or mail template.
![General Settings Data Processor](docs/images/dp_settings_tokesn.png)
We will only need two data sources: 
1. Add Datasource "Activity" - Filter on Activity Type "Appointment" and Activity Status "Scheduled" and at the bottom of the filters Activity contact :: Record Type ID - is one of - "Activity Assignees" (so that the staff member the appointment is with can be added to the reminder) and Save it
![Source Settings Address](docs/images/dps_activityfilter3.png)
![Source Settings Address](docs/images/dps_activityfilter2.png)
![Source Settings Address](docs/images/dps_activityfilter1.png)
1. Add Datasource "Individual" no need to add filters - Jointype "Select fields to join on" (i.e. there has to be a contact to send it to) and join on fields "Conact ID and "Activity::Activity contact::Contact ID (match to contact)"
![Source Settings Address](docs/images/dp_settings_individual.png)

Those were the required Datasources. 

### Fields
Now lets go to the fields that are necessary: Display Name of the staff member, the location of the appointment and the date and time of the appointment. We'll also add the Activity ID and the Contact ID so that we can also create an output Search. With it we can quickly check if the output Token provides the correct fields/tokens.
1. Add field "Activity ID" - Raw Field Value, i.e. what is written in this field in CiviCRM;
1. Add field "Contact ID )match to contact" - Raw field Value and give it a title.
1. Add field "Individual::Display Name" - Raw Field Value and give it a title.
1. Add field "Location" - Raw Field Value and give it a title.
1. Add field "Activity Date" - Date field value and give it a title.
You should now have a list of fields like this:
![Source Settings Website](docs/images/dps_tokenfields.png)

Those are the required fields. Since we don't need any sorting or more filters (we've done those on the datasources), we can now continue with the output. 

Note: the title you give to each field is the name of the token such as it will appear in the tokenlist when writing an email or email template!


### Output (Tokens)
The output we're creating is "Tokens" that can be used by any user of this CiviCRM install to add to manual-, automated- or bulkmail.
1. Go to the Output part of the Dataprocessor and choose "Add Output"
1. From the "Select output*" dropdown, select "Tokens" (which will only be available if you have isntalled the The Data Processor Token Output extension  (	https://lab.civicrm.org/extensions/dataprocessor-token-output)
1. Define the Contact ID field, i.e. in this situation the contact the mail with these tokens will be sent to: "Activity contact::Contact ID (match to contact)"
1. Hidden fields - select the Activity ID and the Contact ID (match to contact) since we don't need those as tokens (but for searches based on this dataprocessor so as to check that all works as expected)
1. No sorting required
1. Save the Data Processor Output

![Source Settings Website](docs/images/dps_tokens_output.png)

Note: once saved, the tokens will be available for selection on the appropriate places when writing emails or email templates. The name of the dataprocessor will also be the name of the tokenset the user gets to see when writing email or emailtemplate. 



### Output (Search)
To make sure that I have defined all properly, I usually also create a Search output so that I can check in CiviCRM that my dataprocessor does what I expect it to do. 
1. Add a second output, but this time select "Activity Search" from the Select output dropwdown. 
1. Add the Search form to a Parent menu in CiviCRM
1. Define the permission, the default is perfect. 
1. The ID field here would be the Activity ID
1. Define whether or not you want the ID field hidden
1. Save the form and look for the Search form under the menu you defined in step 2 here.
![Source Settings Website](docs/images/dps_tokens_output_search.png)

