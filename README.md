# AKsearch

This is the discovery solution for the **AK Bibliothek Wien** (Library of the Chamber of Labour of Vienna). It's based on the open source discovery environment **VuFind**, but is customized to meet the needs of the **AK Bibliothek Wien**.

## Installation
Installation was tested on Ubuntu 20.04 LTS.

1. Make sure you meet the dependencies needed. See also the [VuFind Wiki](https://vufind.org/wiki/installation:ubuntu#detailed_installation_instructions).
1. Clone the repository
   ```
   git clone --recurse-submodules https://biapps.arbeiterkammer.at/gitlab/open/aksearch/aksearch.git
   ```
1. CD into the new directory that was created and run: 
   ```
   composer install
   ```
1. Follow the instructions in the VuFind Wiki from [6. Install VuFind](https://vufind.org/wiki/installation:ubuntu#install_vufind).

## Upgrade
To get the newes updates for AKsearch, run this git pull command from the base directory:
```
git pull --recurse-submodules
```

# Wiki
The general **VuFind** Wiki can be found at [https://vufind.org/wiki](https://vufind.org/wiki)
