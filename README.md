ILIAS HSLU Defaults Plugin
==========================

This Event-Hook-Plugin sets Objects defaults according to what is needed at HSLU.

## Installation Instructions
**1. Add the plugin to your ILIAS installation**

- Navigate to the root directory of your ILIAS installation on the command line
- Execute following command to create the directory for the plugin slot "Repository Object":
```bash
mkdir -p Customizing/global/plugins/Services/EventHandling/EventHook/
```
- Switch to the directory for the plugin slot
```bash
cd Customizing/global/plugins/Services/EventHandling/EventHook/
```
- Clone the git repository from Github
```bash
git clone https://github.com/HochschuleLuzern/HSLUObjectDefaults.git
```

**2. Install the plugin**
- Login in on ILIAS with administrator privileges
- Navigate to Administration -> Extending ILIAS -> Plugins
- Select the Actions-Dropdown and click on *"Install"*

**3. Setup the patches**

**3. Configure the Plugin**
- In the plugin overview, select the option *"Configure"* from the actions-dropdown
- There are 2 optional configs which are used to convert objects for the MediaCast. Both fields have a default value set with the installation
    - Video file type: Define file types, which should be converted to MP4 on upload to MediaCast
    - Audio file type: Define file types, which should be converted to MP3  on upload to MediaCast

**4. Activate the plugin**
- In the plugin overview, select the option *"Activate"*

## Features
- On course creation
    - Set online status on *"Online"*
    - Activate settings for news and news block
- On group creation
    - Activate settings for news and news block
- On adding/removing a participant to a course or group
    - For added participant: Add the course or group object to favorites
    - For removed participant: Remove the course or group object from favorites
- On adding a new media object to a MediaCast
    - If file is in one of the configured media formats: Convert to a streamable MP3 or MP4 format

## Contact
Hochschule Luzern


