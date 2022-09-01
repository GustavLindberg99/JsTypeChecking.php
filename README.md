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


### Arrays

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

You can also do type checking on [rest parameters](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Functions/rest_parameters). To do so, you need to put `Array[Type]` between the `...` and the name of the parameter:

```javascript
function foo(... Array[Number] bar){
    //some code
}

foo(1, 2, 3, 4);
```


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

Note that if a reference type is converted to another type, the reference will be broken *only* if a type conversion is needed:

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

#### Using `implicit` with null arguments

If you use `implicit` together with `nullable` or `strict nullable`, `implicit` must come first:

```javascript
function foo(implicit nullable Number bar){}    //OK
function foo(nullable implicit Number bar){}    //Not OK, won't be processed and will result in a syntax error
```

If you pass `null` or `undefined` as an argument of type `implicit nullable Type`, no type conversion will be done. If you pass `null` or `undefined` as an argument of type `implicit Type` (without `nullable`), it will be converted to `Type` as described above.

#### Using `implicit` with arrays

If an argument is of the type `implicit Array[Type]`, it will first be converted to an array using `[...arg]` and then it will check whether all arguments are of type `Type`.

If an argument is of the type `Array[implicit Type]`, it will first check that the argument is an array (and throw an error if it's not), and then will convert all its elements to `Type` as described above. If an argument is of the type `Array[implicit Type]`, the array will always be copied and the reference will be broken, regardless of whether or not any type conversions are done:

```javascript
function foo(Array[implicit Number] bar){
    foo[0] = 4;
}

let bar = [1, 2, 3];
foo(bar);
console.log(bar);    //[1, 2, 3], the array was copied when passed as an argument
```

This only happens when the argument is of type `Array[implicit Type]`, not when it's of type `Array[Type]`:

```javascript
function foo(Array[Number] bar){
    foo[0] = 4;
}

let bar = [1, 2, 3];
foo(bar);
console.log(bar);    //[4, 2, 3], the array was passed by reference as usual
```


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
