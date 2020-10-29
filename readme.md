# Laravel Vapor Core / Runtime

## Added Support for Custom Response Status Codes

Our team noticed the inability with the original package to utilize custom response status codes when using a load balancer in vapor. After checking AWS documentation and testing we confirmed no issues arise when using the defined 5 classes of status codes 1xx, 2xx, 3xx, 4xx and 5xx. If you want to use custom response status codes with a load balancer in Vapor, please feel free to use this package. We will try to keep it up-to-date with the original repo as best we can.

## Original Readme

[Laravel Vapor](https://vapor.laravel.com) is an auto-scaling, serverless deployment platform for Laravel, powered by AWS Lambda. Manage your Laravel infrastructure on Vapor and fall in love with the scalability and simplicity of serverless.

Vapor abstracts the complexity of managing Laravel applications on AWS Lambda, as well as interfacing those applications with SQS queues, databases, Redis clusters, networks, CloudFront CDN, and more.

This repository contains the core service providers and runtime client used to make Laravel applications run smoothly in a serverless environment. To learn more about Vapor and how to use this repository, please consult the [official documentation](https://docs.vapor.build).
