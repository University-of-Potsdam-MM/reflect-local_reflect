# Reflect - Webservice

## New
* local_reflect_get_completed_feedbacks (returns already answered feedbacks + answers)
* checks if same user already submitted answers to the database (to prevent entries showing up twice)

## Installation:
1. checkout the plugin to moodle/local/reflect/ via ```git clone https://github.com/University-of-Potsdam-MM/reflect-local_reflect.git reflect```
2. log in as  administrator and install the plugin

## Configuration

1. go to Administration -> Plugins -> Local Plugins -> Reflect Web Service
2. add the course id numbers, seperated by line break, for which the webservice shall be activated e.g.

```UPR1```

```UPR-J2```

## Documentation

For an overview of the existing functions go to Administration -> Plugins -> Web services -> External services -> Reflect Service (Functions)

## Functionality
* local_reflect_get_calendar_entries	(returns the calendar entries of the reflection course)
* local_reflect_get_feedbacks	(returns all visible and available feedbacks of a reflection course)
* local_reflect_submit_feedbacks	(submits the feedback values to the specified reflection course)
* local_reflect_enrol_self	(enrols user in a specified reflection course)
* local_reflect_post_feedback	(post general feedback to the forum of the course)
