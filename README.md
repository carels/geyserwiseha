Geyserwise Web Scraper for Home Assistant
=========================================

This code is based on the [Ultimate Web Scraper Toolkit](https://github.com/cubiclesoft/ultimate-web-scraper) and I have included the library for ease of use. I have not been able to get any assistance from Geyserwise to talk directly to their ESP2866 modules to locally gather this data and resorted to scraping the cloud service for the required info. Hopefully one day Geyserwise will give us API access so we can communicate directly to the geyser on the local LAN. 

Features
--------

Get:
* Geyser Temperature
* Element Status
* Last Update Timestamp (last time the geyser communicated with the geyserwise servers)
* Manual Override status
* Holiday Mode status
* Timers
* Expected Status (i.e. are we expecting the geyser to be switched on, see below)

Set:
* Manually override geyser status
* Switch Holiday mode on/off

Requirements
------------

You will need a geyserwise account as well as a suitable geyser with the geyserwise wifi or GSM module installed. You will also need to extract the ddUnit value from your geyserwise account. To find the Unit ID:

* Log in to your geyserwise account at https://geyserwiseonline.com/RemoteManager/dashboard.aspx
* Go to your dashboard
* At this page, view the source of the page
![gw dashboard](https://user-images.githubusercontent.com/6865403/122665725-3c859e00-d1a9-11eb-9648-950d752d8c43.png)
* Search for txtUnitId in the source code of the page until you find this section:
```
            <div style="display:none;">
              <input name="txtUnitId" type="text" value="1234" id="txtUnitId" />
           </div>
```
*  The value= variable contains your unit ID (e.g. 1234 here)

Since HA doesn't run PHP, you will also need to host the files on your own server. If you are not able to do this, you can also use the server in the example, however, keep in mind that you are sending your username & password to a third party (myself) absolutely at your own risk. If you want to go this route, definitely change your geyserwise password to a random password and not one you use on multiple sites.

Home Assistant Configuration
----------------------------

In your sensors section of your configuration.yaml, you need to configure the following:

Configure the main sensor:
```
- platform: rest                        
  name: Geyserwise Stats
  resource: https://gw.carelsolomon.com/geyserwiseGet.php                      
  method: POST                  
  payload: '{"user": "<YOURUSERNAME>", "pass": "<YOURPASSWORD>", "unit": "<UNITID>"}'
  scan_interval: 60       
  json_attributes:                                                                
    - geysertemp                
    - lastupdate                    
    - geyserelement    
    - manualOn                                                                    
    - holidayMode                                                               
    - timers                               
    - geyserExpectedStatus
                                                                                                
```
Replace <YOURUSERNAME>, <YOURPASSWORD> & <UNITID> with your Geyserwise login details and Unit ID.

Side note: I did try to get this to work with a payload from secrets.yaml, but HA doesn't seem to support hat. 

Configure the Set rest command:
```
rest_command:      
  geyserwise_set: 
    url: https://gw.carelsolomon.com/geyserwiseSet.php
    method: POST           
    payload: '{"user": "<YOURUSERNAME>", "pass": "<YOURPASSWORD>", "unit": "<UNITID>", "action": "{{ action }}", "value": "{{ value }}" }'

```
  
Now you can build your sensors and switches:
  
Sensors:
```
- platform: template                                                           
  sensors:                      
    main_geyser_temp:                      
     friendly_name: "Main Geyser Temp"
     value_template: "{{ state_attr('sensor.geyserwise_stats', 'geysertemp')|int }}"
     unit_of_measurement: "C"   
- platform: template                
  sensors:             
    main_geyser_element:                                                          
     friendly_name: "Main Geyser Element"
     value_template: "{{ state_attr('sensor.geyserwise_stats', 'geyserelement') }}"
- platform: template
  sensors:                                                                    
    main_geyser_lastupdate:   
     friendly_name: "Main Geyser Last Update"
     value_template: "{{ state_attr('sensor.geyserwise_stats', 'lastupdate') }}"
- platform: template                                                     
  sensors:                   
    main_geyser_expectedStatus:
     friendly_name: "Main Geyser Expected Status"
     value_template: "{{ state_attr('sensor.geyserwise_stats', 'geyserExpectedStatus') }}"
```

Switches
```
- platform: template
  switches:
    main_geyser_manualoverride:
     friendly_name: "Main Geyser Manual Override"
     value_template: "{{ state_attr('sensor.geyserwise_stats', 'manualOn') }}"
     turn_on:
       service: rest_command.geyserwise_set
       data:
         action: switchgeyser
         value: 'on'
     turn_off:
       service: rest_command.geyserwise_set
       data:
         action: switchgeyser
         value: 'off'
- platform: template
  switches:
    main_geyser_holidaymode:
     friendly_name: "Main Geyser Holiday Mode"
     value_template: "{{ state_attr('sensor.geyserwise_stats', 'holidayMode') }}"
     turn_on:
       service: rest_command.geyserwise_set
       data:
         action: switchholiday
         value: 'on'
     turn_off:
       service: rest_command.geyserwise_set
       data:
         action: switchholiday
         value: 'off'
```

Now you can add your sensors and switches to lovelace:

![image](https://user-images.githubusercontent.com/6865403/122684283-d2024b80-d204-11eb-9340-b150b6e78cb3.png)

Scripts & Automations:
----------------------
  
Problem: Geyserwise does not switch on after power has been restored during the time the geyser is scheduled to be on.
Solution: The following automation will check if the geyser is supposed to be on, and the element temperature is below the expected value. If that is the case we do a manual override to switch on the geyser
  
```
alias: 'Geyserwise - Check geyser status should be on, but temp too low'
description: >-
  Manually switch on the geyser if expected to be on and temperature too low.
  Commonly happens if power went out and was restored during scheduled time slow
trigger:
  - platform: time_pattern
    minutes: '10'
condition:
  - condition: state
    entity_id: sensor.geyserwise_stats
    state: 'on'
    for: '00:10'
  - condition: state
    entity_id: sensor.main_geyser_element
    state: 'off'
  - condition: numeric_state
    entity_id: sensor.main_geyser_temp
    below: '50'
action:
  - service: rest_command.geyserwise_set
    data:
      action: switchgeyser
      value: 'on'
mode: single  
```  
  
Todo
----

* Would be great if someone can convert this to Python so it can run directly off HA rather than 3rd party server

Expected Status
---------------

One major issue with the geyserwise is that the geyser defaults to an OFF status when power has been resumed. This means if you have the geyser set to switch on before 04:00 and 08:00 in the morning and loadshedding ran from 02:00 - 04:30, the geyser will not automatically switch on. You can use the expectedStatus attribute in your HA scripts to decide whether to manually override the geyser status so you don't wake up with a cold shower :)

Weird Stuff
-----------
  
lastupdate sometimes seems to be set in the future. Not sure if this is a problem with the clock on the local geyserwise or geyserwise's own server. The script only passes that timestamp along without modifying it.

Donate
------

Like this? Feel free to donate some BTC here: 
```
BTC: bc1qhadp74cp404wgnu2rgu2r572suamjjzrf8p7cn
```            

Need fast and reliable Internet at your home or office? Use IPComms - https://www.ipcomms.co.za/?referrerCode=1001123
