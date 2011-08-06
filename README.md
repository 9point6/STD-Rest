# STD Rest (API Kit)

The aim of this project is to provide a standard API format in JSON which can then be read by libraries in multiple languages (PHP, Python, Javascript, etc).
Stage one is PHP due to most familiarity and need for me, and creating API sourcefiles for Last.FM, Twitter, Qwerly, and Linked In to ensure that the format is flexible enough.

## Getting Started
For writing an API scheme, see the apis/mobicart.json for the best example.
For using an API scheme (none are finished yet), see index.php.
For help, email richthegeek@gmail.com

## Why?
Currently API's often have either no wrapper libraries or a wrapper library that differs vastly across languages (sometimes for good reason, often just different authors).
By creating a language-neutral format for defining the capabilities of an API, the hope is to be able to just import that API file using the language implementation of choice (apikit.php, apikit.py, etc) and have a working, familiar wrapper available without hassle.


###Short-term targets:
 - Universally readable format for APIs which is flexible enough to allow any RESTful API wrapper to be implemented in it.
 - Create formats for Last.FM, Twitter, Qwerly, Linked In.
 - Figure out flexible authentication scheme (OAuth is easy if everyone would just implement it please?).

### Medium-term targets:
 - Implementations in Python, Ruby, Javascript (CoffeeScript).
 - More API implementations to ensure flexibility.

### Long-term targets:
 - Server and client script-deployment: allow to output a simplified script to langauge of choice which is either a client implementation or the scaffolding for a server implementation.
 - Global domination.


## What about WADL/WSDL?
[WSDL](http://www.ibm.com/developerworks/webservices/library/ws-restwsdl/) + [WADL](http://www.w3.org/Submission/wadl/)

WSDL/WADL has somewhat similar aims, and has plenty of inspiration to offer, but it has some
major drawbacks, and they mostly stem from XML and the ideas it espouses.

It's clearest when illustrated. Google's Custom Search API method in WADL (I think):

<code>

    <application xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:yn="urn:yahoo:yn" xmlns:ya="urn:yahoo:api" xmlns="http://research.sun.com/wadl">
      <grammars>
        <include href="SearchResponse.xsd"/>
      </grammars>
      <resources base="http://www.googleapis.com/customsearch/v1">
        <resource path="">
          <method name="GET" id="search">
            <request>
              <query_variable name="key" type="xsd:string" required="true"/>
              <query_variable name="q" type="xsd:string" required="true"/>
              <query_variable name="cx" type="xsd:string" required="true"/>
              <query_variable name="lr" type="xsd:string" required="true"/>
              <query_variable name="alt" type="xsd:string" fixed="json" />
              <query_variable name="num" type="xsd:int"/>
              <query variable name="safe" type="xds:string" />
              <query_variable name="start" type="xsd:int"/>
              <query_variable name="filter" type="xsd:int"/>
            </request>
            <response>
              <representation mediaType="application/json" element="ga:items"/>
              <fault id="error" status="400" mediaType="application/json" element="ga:error"/>
            </response>
          </method>
        </resource>
      </resources>
    </application>
</code>

In contrast, the same information using STD Rest format:

<code>

    {
      "service" : "Google Custom Search API",
      "url" : "www.googleapis.com/customsearch/v1",
      "secure" : "always",
      "static_fields" : {
        "all": ["key={api_key}", "alt=json"]
      },

      "error_check_path" : "error",

      "validators" : {
        "lr" : "/^lang_.+$/",
        "num" : "/^([0-9]|10)$/",
        "safe" : "/^(high|medium|off)$/",
        "start" : "/^([0-9]|1[0-9]{1,2})$/",
        "filter" : "/^(0|1)$/"
      },

      "methods" : {
        "search" : {
          "required" : "q, cx",
          "optional" : "lr,num,safe,start,filter",
          "validation" : {
            "lr" : "lr",
            "num" : "num",
            "safe" : "safe",
            "start" : "start",
            "filter" : "filter"
          }
        }
      }
    }
</code>

**Pros**: The code is shorter and more of it is dedicated to the actual API description (less repetition), the validation is stricter (saves on wasted queries), and various things that are static in the WADL code can be made (using /vars) configurable, such as the "alt" format flag.

**Cons**: Less descriptive without reference - "query_variable" is obviously a query variable, whereas /methods/\*/required/\* is not as blatant. Doesn't provide any validation on the data that is returned.



## CHANGELOG

### v0.05 - 2011-08-06
1. Changed validation syntax (see mobicart.json). Now to add validation to a field you may do it thusly:
   a) add to method/validation/[key] = [pattern | validators key]\ (where validators key is a key in @validators)
   b) add to @validators[key] = {pattern}, where the validators key is the same as the field key
   c) add to @validators[*] = {pattern, fields: [*,*,key,*,...]} where the fields array (or csv) contains the field key
2. Completed the Mobicart API source - with the exception of not having tested the DELETE methods it all works.

### v0.04 - 2011-08-06
1. Added a CoffeeScript implementation (minus any attempts at authentication). See readme in that dir
2. Fixed one or two bugs with the PHP implementation which regard to handling & and = in keys.

### v0.03 - 2011-08-04
1. Added "secure" flag, which can hold "always", "never", or "auth_only", and sets the http/https use in api/rest.
2. Added whitespace trimming in CSV values (eg method/required).
3. Google Custom Search API as an example.
4. Fixed last.fm after regression for v0.02#3
4. Various minor bugfixes

### v0.02 - 2011-08-03
1. Implemented hooks system for authentication schemes, via @call_hook(hook, data)
2. Added "validators" object to spec.
3. Changed replacement scheme from %key to {key}
4. /static_fields/* all optional
5. /url can now hold replacement strings.
6. Fixed various bugs
7. Further work on Last.FM, specifically authenticated methods
8. Initial Mobicart API, fully documented for easier explanation.


### v0.01 - 2011-08-02
1. Initial Last.FM api working on simple test cases.
2. No Authentication yet, need to figure out how to allow custom auth schemes.
3. The apis/lastfm.json should show you well enough how it works, I'll write a proper schema eventually.