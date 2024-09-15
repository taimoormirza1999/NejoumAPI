# Nejoum API

![api](https://img.shields.io/badge/Nejoum-API-blue) [![Deploy to AWS](https://github.com/Nejoum-aljazeera/Nejoum_API/actions/workflows/main.yml/badge.svg)](https://github.com/Nejoum-aljazeera/Nejoum_API/actions/workflows/main.yml)   [![PHP Version Require](http://poser.pugx.org/phpunit/phpunit/require/php)](https://packagist.org/packages/phpunit/phpunit)


# Getting started


## Installation

Use [Git](https://pip.pypa.io/en/stable/) to clone the repository.

    git clone https://github.com/Nejoum-aljazeera/Nejoum_API.git
    cd Nejoum_API
    composer install


Make a branch with the feature name that you are working on

    git branch   FeatureName_05|01
    git checkout FeatureName_05|01


Set up your environment and install dependencies if necessary.
This project is built using [Lumen](https://lumen.laravel.com/docs).

[![Build Status](https://travis-ci.org/laravel/lumen-framework.svg)](https://travis-ci.org/laravel/lumen-framework) [![Total Downloads](https://img.shields.io/packagist/dt/laravel/lumen-framework)](https://packagist.org/packages/laravel/lumen-framework) [![Latest Stable Version](https://img.shields.io/packagist/v/laravel/lumen-framework)](https://packagist.org/packages/laravel/lumen-framework) [![License](https://img.shields.io/packagist/l/laravel/lumen)](https://packagist.org/packages/laravel/lumen-framework)

 
> **Note:** In the years since releasing Lumen, PHP has made a variety of wonderful performance improvements. For this reason, along with the availability of [Laravel Octane](https://laravel.com/docs/octane), we no longer recommend that you begin new projects with Lumen. Instead, we recommend always beginning new projects with [Laravel](https://laravel.com).
  
 

## Guidelines  👨‍💻
### Branch Per Feature (BPF)

Branch Per Feature works on the basic premise that each feature or piece of work gets its own branch

>* All work is done in feature branches
>* All feature branches start from a common point (pulled from the last release of origin/main)
>* Merge regularly into an integration branch to resolve conflicts then merge into the development branch(QA)
>* The QA branch is used to solve conflicts then it gets merged into main.
>* Integration tests are occurred in this branch to test features correlation with each other

## Deployment 

The CI pipeline is linked with the main branch, once updated it will automatically take effect in the live version, therefor don't merge code until task is completed 


```shell
git checkout features/addXY
git commit 
git pull origin main # optional, to update your code with the last code from main 
git push origin features/addXY
## the merge is connected to the live hence only certain people have access to merging with main 
```

###### Note: follow your own way as long as the result is the same. ✅


 


## Notes 📝
The main code shall be treated as production code, meaning that the feature shall be **finalized** before merging 








###### feel free to contribute and enhance the readme, this would be helpful for the newcomers to easily catch up.



 
 
  


 
