# how to use:

To use this module, install it and run the following from the command line: 
```shell
    # search
    vendor/bin/sake dev/tasks/search-all-tables-and-fields s=foo 

    # replace
    vendor/bin/sake dev/tasks/search-all-tables-and-fields s=foo r=bar c=0 f=0
```
- s = search term
  
- r = replace terms (optional)
  
- c = is case sensitive? (optional)
  
- f = is full cell value match (as opposed to partial match) (optional)
