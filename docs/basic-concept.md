# Basic Concept of the Form Processor Extension

The **Form Processor** extension allows you to specify the data that will be processed with a form on your public website.
In CiviCRM you specify what data you want to display on the form, if and what default values you want to load and what should happen with the data that is submitted with the form.

For example: I want a form that allows me to enter my name and email address so I can sign up for the newsletter.
You can specify all that in the **Form Processor** and also specify that a new contact should be created if one can not be found with the email address, and that the contact found or created should be added to the newsletter group in CiviCRM.

In this chapter we will briefly discuss the forms and screens you need to go through if you are specifying a form with the **Form Processor**.

!!! Note "Techie stuff"
    On a technical level: the extension actually generates an API action for the FormProcessor API entity.

## Form Processors Page

Once you installed the **Form Processor** extension you will have an additional option in the **Administer>Automation>Form processors** option which will lead you the the first view of all the form processors in your installation.
This will be empty if you just installed the extension.

![Form Processors Page](/images/init-form.png)

## New Form Processor - define form processor
On this page you can click on the **New Form Processor** button to create a new form processor. If you do this you will see a form like the one below:

![New Form Processor](/images/new-processor.png)

On the top of the form you can see that there are **two** tabs:

1. the *Define form processor* tab, which you will see if you start.
On this tab you specify generic information about your form, the inputs on your form (with defaults values and/or validation if you want to) and what should happen in CiviCRM with the data from the form once it has been submitted.
1. the *Retrieval of defaults* tab. On this tab you can specify what defaults should be loaded in your form. We will discuss this in the next section.

In this section we will deal with the *define form processor* tab.

### General form processor information

In the top part of the form you can enter the general form processor information:

* have to specify a *title* for the form processor
* a *name* will be suggested based on the *title* but can be changed if you click on the lock behind the field first!

!!! Note
    A *name* can not contain any spaces! And should be unique!

* you can specify a detailed description (and it makes sense to do so if you expect to build a few forms!)
* you can tick if the form processor should be enabled (on by default)
* you can select what permissions are required to be able to process the form. For example you could decide you can only send your address details if you have the *CiviCRM:edit my contact* permission.

It will look something like this:

![Top part of the form](/images/new_first.png)

### Input fields on your form processor

In the next part under the heading **Inputs** you can specify the input fields that should be on your form.

For each field you can select the type of field (short text, numeric no decimal, date, yes/no, option group etc.), specify a title for the input file and add validation if that is required. Let's for example take the first name, last name and email.

Adding the input field for the *email* will probably have to look like this:

![Input field for email](/images/new-input-email.png)

Once I have added all the fields (and as you can see I have added the validation that first and last name should at least have 3 characters) the list will look like this:

![List of input fields](/images/new-list-input-fields.png)

What I have done now is specify that I expect my form to show 3 fields for the website visitor to enter: First Name, Last Name and Email.

### Actions on your form processor

In the final part of the specification of a form processor you can specify what needs to happen once CiviCRM receives the data from the form.
We do that by adding *actions* to the form processor.

So in our example, we should find a contact by the email. Next the contact found should be added to the newsletter group.
Initially the part of the form where we can specify actions will look like this:

![Action part of the form](/images/new-action-part.png)

If you click on the action select box you will get a list of actions that are already available because some of the funding organizations needed that action.
As time goes by and more people start using and enhancing the [**Action Provider** extension][actionproviderrepo], the list will grow.

!!! Note "Create your own"
    It is possible to develop your own specific actions, or indeed generic ones that others can use too! Check the relevant sections in [Example of Email Preferences](email-preferences.md)

In this example we have a first step: find the contact with the data from the form.

![Find contact action](/images/action-find-contact.png)

Once I have found the contact it should be added to the newsletter group.
If I select the *add to group* action I can select the group and specify that I want to use the contact ID found in the previous action:

![Add to newsletter group action](/images/action-add-to-group.png)

## New Form Processor - retrieval of defaults

With the **Form Processor** extension it is possible to pre-load your form with default data, for example for a *My Address Data* form.
We could pass a parameter (a checksum for example) in the URL of the form and based on that retrieve the current values from CiviCRM and prepopulate the form with this data.

This can be specified in the *Retrieval of defaults* tab. Once I have ticked the *Enable default data retrieval?* option I will see a form like this:

![New retrieval of defaults](/images/new-retrieval.png)

As you can see the input fields from my form processor have already been loaded.
I can specify here what criteria I want to use, in this example I have specified a short text named *checksum*.

At the *retrieval methods* part I can select how I want to retrieve my data.
Here I would like an action called *Find contact with checksum*. Unfortunately that is not available yet because it has not been developed yet, but you get the gist.
And it will be developed in the section [Email Preferences](email-preferences.md).

For each of my input fields I can finally specify what data should be loaded here, which should come from the result of my action.

!!! Note "Techie stuff"
    On a technical level: If you have enabled default data retrieval it actually generates an API action for the FormProcessorDefaults API entity.

## Output Handler

At the bottom of the form where you edit or create your form processor there is also a bit about an *output handler*:

![Output Handler](/images/output-handler.png)

This gives you the option to manipulate the output that is sent back to the public website once all the actions have been executed.
If you do not specify anything, the default value of *send everything* then all the data involved is sent back (inputs, output from actions etc.).

If you decide for example you only want to send the ID of the contact back you can select the *decide what to send* and specify what from all the available data should be send back and how it is called.

## That's it!
If you click on the *Retrieval of defaults* tab in the **New Form Processor** form you will get a form like this:

And that is all! A form to be used without coding. Once I saved the form processor I can see at the top of the form what API action I can use:

![API to be used](/images/api-to-be-used.png)

So far the basic concept. Obviously the public website part needs to be done too, check the [Example Sign Up Newsletter](sign-up-newsletter.md) section for that part, this section just covers the basis principles.


[actionproviderrepo]:https://lab.civicrm.org/extensions/action-provider

