# Overview

jsTypeChecking.php is a PHP script that lets you do type checking on function arguments in Javascript as follows:

```javascript
function foo(String a, Number b, Array c){
    //some code
}
```

You then pass this code as an argument to the `jsTypeCheck` function in a PHP script and it outputs Javascript code that browsers can understand. For example, for the code above, it will output the following:

```javascript
function foo(a, b, c){
    if(typeof a != "string"){
        throw TypeError("Expected parameter a to be of type String, got " + a?.constructor?.name);
    }

    if(typeof b != "number"){
        throw TypeError("Expected parameter b to be of type Number, got " + b?.constructor?.name);
    }

    if(!(c instanceof Array)){
        throw TypeError("Expected parameter c to be of type Array, got " + c?.constructor?.name);
    }

    //some code
}
```

Unlike complete languages like Typescript, this is only a very simple extension of standard Javascript, which has a few advantages:
- Only minimal changes are made to your code when compiling it to standard Javascript, which makes it easier to use the browser's debugger.
- The PHP script is relatively fast, which can be necessary on servers with limited capacity.


# Setup

To set up jsTypeChecking.php, simply download the file [jsTypeChecking.php](https://raw.githubusercontent.com/Gustav-Lindberg/JsTypeChecking.php/main/jsTypeChecking.php) and save it in the same folder as your PHP script. You can then include it in your PHP script like this:

```php
require_once("jsTypeChecking.php");
```


# Usage

## Server side code

To use jsTypeChecking.php, you first need to write some Javascript code with type checking as explained in the section below. When you have your Javascript code, pass it as an argument to the `jsTypeCheck` function of jsTypeChecking.php. Here is an example:

```php
<?php
require_once("jsTypeChecking.php");

$jsCode = "
function foo(String a, Number b, Array c){
    //some code
}
";

echo jsTypeCheck($jsCode);

/*
Output:
function foo(a, b, c){
    if(typeof a != "string"){
        throw TypeError("Expected parameter a to be of type String, got " + a?.constructor?.name);
    }

    if(typeof b != "number"){
        throw TypeError("Expected parameter b to be of type Number, got " + b?.constructor?.name);
    }

    if(!(c instanceof Array)){
        throw TypeError("Expected parameter c to be of type Array, got " + c?.constructor?.name);
    }

    //some code
}
*/
```

Note that the type checking is done at runtime. If you try to supply a parameter of an incorrect type, the PHP script will not warn you, but you will get a Javascript `TypeError` when you try to run it.

In practice, you will probably want to put your code in a seperate .js file, in which case you will probably want to combine the `jsTypeCheck` function with PHP's built-in `file_get_contents`:

```php
echo jsTypeCheck(file_get_contents("file.js"));
```


## Client side code

### Basic usage

To apply type checking to a function argument, simply add the name of the type before the argument. For example, to make sure that an argument is a regular expression, you can do this:

```javascript
function foo(RegExp bar){
    //some code
}

foo(/./);    //OK
foo(3);    //Will throw a type error
```

You are not required to do type checking on all parameters. For example, in the following code, the first argument must be a `RegExp`, but the second argument can be any type:

```javascript
function foo(RegExp a, b){
    //some code
}

foo(/./, 3);    //OK
foo(/./, "Hello World!");    //Also OK
foo(3, "Hello World!");    //Error, the first argument must be a RegExp
```

More generally, code accepted by jsTypeChecking.php is a superset of regular Javascript, which means that any Javascript code that would otherwise be valid is also valid using jsTypeChecking.php.


### Primitive types

To check for a primitive type (strings, numbers, booleans or symbols), use the types `String`, `Number`, `Boolean` or `Symbol`. These types will only allow primitive types, not objects:

```javascript
function foo(String bar){
    //some code
}

foo("Hello World!");    //OK
foo(new String("Hello World!"));    //Error
```


### Nullable arguments

By default, passing `null` or `undefined` as a typed argument will throw an error:

```javascript
function foo(String bar){
    //some code
}
 
foo(null);    //Error
foo(undefined);    //Error
```

If you want to allow a specific argument to be `null` or `undefined`, you can write `nullable` before the type:

```javascript
function foo(nullable String bar){
    //some code
}

foo(null);    //OK
foo(undefined);    //OK
foo("Hello World!");    //Still OK
```

If you want to allow the argument to be `null` but *not* `undefined`, you can use `strict nullable`:

```javascript
function foo(strict nullable String bar){
    //some code
}

foo(null);    //OK
foo(undefined);    //Error
foo("Hello World!");    //OK
```


### Arrays and other containers

#### Arrays

You can require an argument to be an array just like you can with any other type:

```javascript
function foo(Array bar){
    //some code
}

foo([1, "Hello World!", null, true]);
```

However, you might want to only allow arrays that contain a certain type. To do so, specify as a type `Array[Type]`:

```javascript
function foo(Array[Number] bar){
    //some code
}

foo([1, 2, 3]);    //OK
foo(["Hello", "World"]);    //Error, passing an array of strings when expecting an array of numbers
foo([1, "Hello World!"]);    //Error, this array contains a string, but all elements of the array are expected to be numbers
```

You can also use `nullable` with arrays:

```javascript
function foo(Array[nullable Number] bar){
    //some code
}

foo([1, 2, 3]);    //OK
foo([1, 2, null]);    //OK
```

You can't use type checking in nested arrays, things like `Array[Array[Type]]` won't be processed (though `Array[Array]` works perfectly fine).

#### Rest parameters

You can also do type checking on [rest parameters](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Functions/rest_parameters). To do so, you need to put `Array[Type]` between the `...` and the name of the parameter:

```javascript
function foo(... Array[Number] bar){
    //some code
}

foo(1, 2, 3, 4);
```

#### Sets

Sets work just like arrays, you can (but don't have to) require the contents of the set to be of a certain type by using `Set[Type]`:

```javascript
function foo(Set[Number] bar){
    //some code
}

foo(new Set([1, 2, 3]));    //OK
foo(new Set(["Hello", "World"]));    //Error, passing a set of strings when expecting a set of numbers
foo(new Set([1, "Hello World!"]));    //Error, this set contains a string, but all elements of the set are expected to be numbers
```

#### Objects

If you use objects as maps where the keys are strings, you can use `Object[Type]` to check that all the values are of type `Type`:

```javascript
function foo(Object[Number] bar){
    //some code
}

foo({a: 1, b: 2, c: 3});    //OK
foo({a: "Hello", b: "World"});    //Error, the values are strings but should be numbers
foo({a: 1, b: "Hello World!"});    //Error, one of the values is a string, but all values are expected to be numbers
```

If you give your object methods, be careful to make them non-enumerable. For example, this will throw an error:

```javascript
function foo(Object[Number] bar){
    //some code
}

foo({a: 1, b: 2, c: 3, someMethod: function(){}});    //Error, one of the values is a function, but all values are expected to be numbers
```

For this to work, you need to make the methods non-enumerable, for example using `Object.defineProperty()`:

```javascript
function foo(Object[Number] bar){
    //some code
}

let obj = {a: 1, b: 2, c: 3};
Object.defineProperty(obj, 'someMethod', {
    value: function(){}
});
foo(obj);    //OK, someMethod is not enumerable and won't be checked when checking that all values are numbers
```

Another thing to be aware of is that all classes inherit from `Object`, so type checking for `Object` won't prevent the argument from being of another object type:

```javascript
function foo(Object[Number] bar){
    //some code
}

foo([1, 2, 3]);    //OK, arrays are objects
foo([1, 2, 3, "Hello World!"]);    //Error, all the values should be numbers, but this array contains a string
foo(new Set([1, 2, 3]));    //OK, sets are objects
foo(new Set([1, 2, 3, "Hello World"]));    //OK, sets don't have any enumerable properties, so no type checking will be done on the contents
foo(function(){});    //OK, functions are objects with no enumerable properties
foo(3);    //Error, primitives aren't objects
```

#### Maps

You want to make sure that the keys and values of a map are of specific types by using `Map[KeyType, ValueType]`:

```javascript
function foo(Map[String, Number] bar){
    //some code
}

let myMap = new Map();
myMap.set("Hello", 1);
myMap.set("World", 2);
foo(myMap);    //OK
myMap.set(3, 4);
foo(myMap);    //Error, we just added 3 as a key which is a number, but all keys should be strings

let myOtherMap = new Map();
myOtherMap.set("Hello", "World");
foo(myOtherMap);    //Error, one of the values is a string, but all the values should be numbers
```

If you do type checking on maps like this, you have to specify both a key type and a value type, you can't specify one but not the other (but you can specify neither by doing `function foo(Map bar){...}`).


### Classes

You can do type checking in class methods just like you can in regular functions:

```javascript
class Foo{
    constructor(String bar){
        //some code
    }
    
    myMethod(Number baz){
        //some code
    }
}

let foo = new Foo("Hello World!");
foo.myMethod(5);
```

You can also require a function to take an instance of a given class as a parameter. To do so, simply supply the name of the class as the type:

```javascript
class Bar{
    //some code
}

function foo(Bar bar){
    //some code
}

let bar = new Bar();
foo(bar);
```

This also works well with inheritance:

```javascript
class Base{
    //some code
}

class Derived extends Base{
    //some code
}

function foo(Base bar){
    //some code
}

let bar = new Derived();
foo(bar);
```


### Implicit conversions

If you want to convert the argument to the correct type instead of throwing an error, you can write `implicit` before the type:

```javascript
function foo(implicit String bar){
    console.log(bar);
}

foo({});    //Prints '[object Object]' (since the argument was implicitly converted to a string)
```

The implicit conversions work the following way:
- If the argument is an instance of the specified type or an instance of a subclass of the specified type, no type conversion will be done.
- If the type is `implicit String`, `implicit Number`, `implicit Boolean` or `implicit Symbol`, the argument will be converted using `String(arg)`, `Number(arg)`, `Boolean(arg)` or `Symbol(arg)` respectively.
- If the type is `implicit Array`, the argument will be converted using `[...arg]`. Note that this will throw an error if the argument isn't iterable.
- Otherwise the argument will be converted using `new Type(arg)`. Note that this might throw an error depending on what's inside the constructor of `Type`.

**Warning:** Using `implicit Function` is dangerous, since it will compile into `new Function(arg)` which is similar to `eval(arg)`.

You can't use `implicit` when checking the type of the contents of a container, things like `Array[implicit Type]` won't be processed. However, `implicit Array[Type]` works fine, it will first convert the argument to an array, and then check that all elements are of type `Type`. Sets, objects and maps work similarly.

If a reference type is converted to another type, the reference will be broken *only* if a type conversion is needed:

```javascript
class Foo{
    constructor(Number x){this.x = x;}
}

class Bar{
    constructor(Foo foo){this.x = foo.x;}
}

function test(implicit Foo foo){
    foo.x = 3;
}

let foo = new Foo(1);
test(foo);
console.log(foo.x);    //3, foo was passed by reference since no type conversion was needed

let bar = new Bar(1);
test(bar);
console.log(bar.x);    //1, the argument was implicitly converted to a new Foo object, the original Bar object remained untouched
```

If you use `implicit` together with `nullable` or `strict nullable`, `implicit` must come first:

```javascript
function foo(implicit nullable Number bar){}    //OK
function foo(nullable implicit Number bar){}    //Not OK, won't be processed and will result in a syntax error
```

If you pass `null` or `undefined` as an argument of type `implicit nullable Type`, no type conversion will be done. If you pass `null` or `undefined` as an argument of type `implicit Type` (without `nullable`), it will be converted to `Type` as described above.


### Arrow functions

Type checking in arrow functions is only supported with the syntax `(arguments) => {body}`. jsTypeChecking.php won't process arrow function with other syntaxes (such as `argument => {body}` or `(arguments) => body`), which means that attempting to add type specifiers to these arrow functions will result in the browser throwing a syntax error. The reason for this is because supporting this would make the code more complicated and more likely to contain bugs.

```javascript
//Supported
let a = (Number x) => {
    return x**2;
};

//Not supported, will result in a syntax error
let b = Number x => {
    return x**2;
};
let c = (Number x) => x**2;
let d = Number x => x**2;

//The following code is supported since it's valid in regular Javascript
let e = x => {
    return x**2;
};
let f = (x) => x**2;
let g = x => x**2;
```


# Compatibility

Server side, the PHP script requires PHP 7.1 or later to run.

Client side, the output code makes use of the [optional chaining operator](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Optional_chaining), which requires Chrome/Edge 80 or later, Firefox Desktop 74 or later, or Firefox for Android 79 or later ([full list of browser support](https://caniuse.com/mdn-javascript_operators_optional_chaining)). If you use recent features in your own Javascript code, the browser must be compatible with those features as well, as jsTypeChecking.php does not insert any polyfills.
