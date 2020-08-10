# Introduction

**[Data Processor][dataprocessorrepo]** is an extension originally developed by [CiviCooP][civicoop], mainly funded by [Velt][velt], [Barnekreftforeningen][barnekreft], [Zorg om Boer en Tuinder][boertuinder], [NIHR BioResource][bioresource], [Civiservice.de][civiservice] and CiviCooP themselves.

The aim of the extension is to create an engine to quickly create data overviews without coding that can be used within CiviCRM but also outside of CiviCRM, like on the public website.

And without the requirement of having CiviCRM installed on the same server. So I could have my public website on server A and CiviCRM installed on server B and still show data from CiviCRM on my public website using the Data Processor extension.

The configuration we had in mind when developing is a CiviCRM installation on a different server than the public website and communication with CiviCRM using the CiviMRF framework (see [the CiviMRF GitHub repo][cmrf-repo]).

!!! Note "About CiviMRF"
    **CiviMRF** is a framework that enables communication between a public website and CiviCRM on a separate server. It contains a generic core, a Drupal 7 specific implementation, a Wordpress specific implementation, a Drupal 8 specific implementation etc.

This is not required, you can just as well use the **Data Processor** extension to communicate with a CiviCRM installation on the same server as the public website.


## Contents

This guide is an attempts to explain the basic concept of the **Data Processor** and a few examples on how to use the data processor.

In some examples other related extensions are required as well, we will mention that in the examples.

The basic concepts of the **Data Processor** are explained on:

- [Basic Concepts](basic-concept.md)

This guide also contains a few examples of use cases:

- [How to publish a list of contact name, country and website on the public website (Drupal 8 website)](usecase1.md)

## CiviCRM versions

The **Data Processor** extension has initially been developed with CiviCRM 4.7 and has been tested with CiviCRM 5.x on various sites.

!!! Note
    If you want the **Data Processor** updated to a newer version you can do so. Alternatively, if you want us to do it and have some funding, contact Jaap Jansma (<jaap.jansma@civicoop.org>) or Erik Hommel(<erik.hommel@civicoop.org>). You can also find them on the [CiviCRM Mattermost Channel][mattermost] using the handles **jaapjansma** or **ehommel**.

## Screen prints

In this guide you will find a number of screenshots. These were all taken from an installation with the [Shoreditch theme][shoreditch].

[civicoop]: http://www.civicoop.org/
[velt]:https://velt.be/
[barnekreft]:https://www.barnekreftforeningen.no/
[civiservice]:https://civiservice.de/
[bioresource]:https://bioresource.nihr.ac.uk/
[boertuinder]:https://www.zorgomboerentuinder.nl/
[cmrf-repo]:https://github.com/CiviMRF
[dataprocessorrepo]:https://lab.civicrm.org/extensions/dataprocessor
[mattermost]:https://chat.civicrm.org/civicrm/
[shoreditch]:https://civicrm.org/extensions/shoreditch
