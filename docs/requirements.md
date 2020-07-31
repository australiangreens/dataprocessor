# Requirements

The **Form Processor** extension was developed with CiviCRM on a separate server than the public website in mind.

There are some requirements on the CiviCRM side and some requirements on the public website side to be able to use the **Form Processor**.

## Requirements on the CiviCRM side

1. The **Form Processor** extension has to be active (see [Form Processor on Gitlab][formprocessorrepo])
1. The **Action Provider** extension has to be active (see [Action Provider on Gitlab][actionproviderrepo])

## Requirements on the public website side

As mentioned we assume that CiviCRM is on another server than the public website.

The **CiviMRF** framework is used to communicate with CiviCRM from the public website. **CiviMRF** is a *CMS agnostic* framework that could be used on any CMS in theory.
In reality there are **CiviMRF** implementations for Drupal 7, Drupal 8 and Wordpress.
So at the time we are writing this documentation you can use the **Form Processor** in combination with a Wordpress, Drupal 7 or Drupal 8 public website sitting on another server.

### Requirements for Drupal 7

This configuration has been used most at the moment of writing.

1. The Webform module - [Webform][webform]
1. The CiviMRF Core and CiviMRF webform module - [CMRF core][cmrfcore] contains both
1. The CiviMRF Form Processor module - [CRMF Form Processor][cmrfformprocessor]

## Requirements for Drupal 8

1. The Webform module - [Webform][webform]
1. The CiviMRF Core and CiviMRF webform module - [CMRF core][cmrfcore8] - this is still in development!

!!! Note
    The CiviMRF Form Processor is not available yet for Drupal 8. Feel free to develop this, or if you want to fund the development by [CiviCooP][civicoop], contact Jaap Jansma (<jaap.jansma@civicoop.org> or using the handle **jaapjansma** on the [CiviCRM Mattermost Channel][mattermost]).

## Requirements for Wordpress

1. The plugin Contact Form 7 - [Contact Form 7][contactform7]
1. The plugin Contact Form 7 CiviCRM Integration - [Contact Form 7 CiviCRM][contactform7civi]

!!! Note
    Mikey O'Toole (<mikey@mjco.uk>) has a fair amount of experience with using the **Form Processor** in combination with a Wordpress public website. You can find him on the [CiviCRM Mattermost Channel][mattermost] using the handle **mikeymjco**.

## Any other CMS

If you do want to use any other CMS for your public website you can certainly do so but if you want to use the Form Processor you would have to develop a CMS specific implementation of the CiviMRF framework. If you want to know more about this you can contact either [CiviCooP][civicoop] or [Systopia][systopia].

[actionproviderrepo]:https://lab.civicrm.org/extensions/action-provider
[formprocessorrepo]:https://lab.civicrm.org/extensions/form-processor
[civicoop]:https://civicoop.org/
[systopia]:https://www.systopia.de/
[webform]:https://www.drupal.org/project/webform
[cmrfcore]:https://github.com/CiviMRF/cmrf_core/releases
[cmrfformprocessor]:https://github.com/CiviMRF/cmrf_form_processor/releases
[cmrfcore8]:https://lab.civicrm.org/frontkom/cmrf_core_d8
[contactform7]:https://wordpress.org/plugins/contact-form-7/
[contactform7civi]:https://wordpress.org/plugins/contact-form-7-civicrm-integration/
[mattermost]:https://chat.civicrm.org/civicrm/
