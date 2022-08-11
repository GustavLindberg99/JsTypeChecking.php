<?php
/*
jsTypeChecking.php by Gustav Lindberg version 1.1.0
https://github.com/GustavLindberg99/JsTypeChecking.php
*/

function jsTypeCheck(string $code): string{
    //Function for checking if we're in a string or a comment
    $updateStringDelimiter = function(string &$code, int $j, ?string &$stringDelimiter): void{
        //Find number of backslashes for strings
        if($code[$j] == '"' || $code[$j] == '\'' || $code[$j] == '`'){    //For performance, only count the number of backslashes if we're going to use it
            if(preg_match("/[^\\\\]\\\\*$/", substr($code, 0, $j), $backslashes)){
                $numberOfBackslashes = strlen($backslashes[0]) - 1;
            }
            else{
                $numberOfBackslashes = $j;
            }
        }

        //Comments
        if($stringDelimiter == null && $code[$j] == '/' && $code[$j+1] == '/'){
            $stringDelimiter = "//";
        }
        else if($stringDelimiter == "//" && ($code[$j] == "\r" || $code[$j] == "\n")){
            $stringDelimiter = null;
        }
        else if($stringDelimiter == null && $code[$j] == '/' && $code[$j+1] == '*'){
            $stringDelimiter == "/*";
        }
        else if($stringDelimiter == "/*" && $code[$j] == '*' && $code[$j+1] == '/'){
            $stringDelimiter = null;
        }

        //Strings
        else if($code[$j] == '"' && $numberOfBackslashes % 2 == 0){
            if($stringDelimiter == '"'){
                $stringDelimiter = null;
            }
            else if($stringDelimiter == null){
                $stringDelimiter = '"';
            }
        }
        else if($code[$j] == '\'' && $numberOfBackslashes % 2 == 0){
            if($stringDelimiter == '\''){
                $stringDelimiter = null;
            }
            else if($stringDelimiter == null){
                $stringDelimiter = '\'';
            }
        }
        else if($code[$j] == '`' && $numberOfBackslashes % 2 == 0){
            if($stringDelimiter == '`'){
                $stringDelimiter = null;
            }
            else if($stringDelimiter == null){
                $stringDelimiter = '`';
            }
        }
    };

    //Iterate over each character of the code
    $globalStringDelimiter = null;
    $depth = 0;
    $classDepths = [];
    $reservedKeywords = ["await", "break", "case", "catch", "class", "const", "continue", "debugger", "default", "delete", "do", "else", "enum", "export", "extends", "false", "finally", "for", "function", "if", "implements", "import", "in", "instanceof", "interface", "let", "new", "null", "package", "private", "protected", "public", "return", "super", "switch", "static", "this", "throw", "try", "true", "typeof", "var", "void", "while", "with", "yield"];
    for($i = 0; $i < strlen($code); $i++){
        //If we're in a string or a comment, don't do anything
        $updateStringDelimiter($code, $i, $globalStringDelimiter);
        if($globalStringDelimiter != null){
            continue;
        }

        //Check if we're in a class (since methods don't use the function keyword)
        if($code[$i] == '{'){
            $depth++;
        }
        else if($code[$i] == '}'){
            $depth--;
            $classDepths = array_diff($classDepths, [$depth]);
        }
        if(preg_match("/^class\s+[\w$]+(\s+extends\s+[\w$]+)?\s*\{/", substr($code, $i), $classMatch)){
            $classDepths[] = $depth;
        }

        //Check if this is a function declaration (this will also be true if it's a statement in parentheses, but we will differentiate that from arrow functions or methods later)
        if(preg_match("/^(function(\s+[\w$]+)?\s*)?\(/", substr($code, $i), $declarationMatch)){
            $isClassMethod = in_array($depth - 1, $classDepths);
            $isArrowFunction = !$isClassMethod && strpos(substr($code, $i), "function") !== 0;
            $numberOfParentheses = 1;
            $stringDelimiter = null;

            //Check what's after the parentheses to see if it's an arrow function
            for($j = $i + strlen($declarationMatch[0]); $numberOfParentheses > 0 && $j < strlen($code); $j++){
                $updateStringDelimiter($code, $j, $stringDelimiter);
                if($code[$j] == '('){
                    $numberOfParentheses++;
                }
                else if($code[$j] == ')'){
                    $numberOfParentheses--;
                }
            }

            //Check if it's actually a function
            if(preg_match($isArrowFunction ? "/^\s*=>\s*\{/" : "/^\s*\{/", substr($code, $j), $declarationEndMatch)){
                $numberOfParentheses = 1;
                $stringDelimiter = null;
                $variablesWithTypes = [];

                //Get the list of typed parameters and remove the types (since the Javascript interpreter doesn't understand typed parameters)
                for($j = $i + strlen($declarationMatch[0]); $numberOfParentheses > 0 && $j < strlen($code); $j++){
                    $updateStringDelimiter($code, $j, $stringDelimiter);
                    if($code[$j] == '(' && $stringDelimiter == null){
                        $numberOfParentheses++;
                    }
                    else if($code[$j] == ')' && $stringDelimiter == null){
                        $numberOfParentheses--;
                    }
                    else if($numberOfParentheses == 1 && $stringDelimiter == null && preg_match("/^(?:[^\w$])((?:(?:strict\s+)?nullable\s*)?[\w$]+(?:\s*\[\s*(?:(?:strict\s+)?nullable\s+)?[\w$]+\s*\])?)\s+([\w$]+)/", substr($code, $j - 1), $variableWithType)){
                        if(empty(array_intersect($variableWithType, $reservedKeywords))){
                            $variablesWithTypes[$variableWithType[2]] = $variableWithType[1];
                            $code = substr_replace($code, $variableWithType[2], $j, strlen($variableWithType[0]) - 1);
                            //No need to adjust i and j, it only deletes stuff after these positions
                        }
                    }
                }

                //Add if conditions for each typed parameter to throw a type error if the wrong type is passed
                $typeChecking = "";
                foreach($variablesWithTypes as $variable => $type){
                    $isNullable = preg_match("/^(strict\s+)?nullable\s/", $type);
                    $isStrictNullable = preg_match("/^strict\s+nullable\s/", $type);
                    if($isNullable){
                        $type = preg_replace("/^(strict\s+)?nullable\s*/", "", $type);
                    }
                    $contentsType = null;
                    $contentsIsNullable = false;
                    $contentsIsStrictNullable = false;
                    if(preg_match("/^Array\s*\[\s*((?:nullable\s+)?[\w$]+)\s*\]/", $type, $contentsTypeMatches)){
                        $type = "Array";
                        $contentsType = $contentsTypeMatches[1];
                        $contentsIsNullable = preg_match("/^(strict\s+)?nullable\s/", $contentsType);
                        $contentsIsStrictNullable = preg_match("/^strict\s+nullable\s/", $contentsType);
                        if($contentsIsNullable){
                            $contentsType = preg_replace("/^(strict\s+)?nullable\s*/", "", $contentsType);
                        }
                    }
                    $typeChecking .= "\nif(";
                    if($isStrictNullable){
                        $typeChecking .= "$variable !== null && ";
                    }
                    else if($isNullable){
                        $typeChecking .= "$variable != null && ";
                    }
                    switch($type){
                        case "String":
                        case "Number":
                        case "Boolean":
                        case "Symbol":
                            $typeChecking .= "typeof($variable) != '" . strtolower($type) . "'";
                            break;
                        default:
                            $typeChecking .= "!($variable instanceof $type)";
                            break;
                    }
                    $typeChecking .= "){\n    throw TypeError(\"Expected parameter $variable to be of type $type";
                    if($isNullable){
                        $typeChecking .= " or null";
                        if(!$isStrictNullable){
                            $typeChecking .= "/undefined";
                        }
                    }
                    $typeChecking .= ", got \" + $variable?.constructor?.name);\n}\n";
                    if($contentsType != null){
                        $contentsTypeChecking = "a => ";
                        if($contentsIsStrictNullable){
                            $contentsTypeChecking .= "a !== null && ";
                        }
                        else if($contentsIsNullable){
                            $contentsTypeChecking .= "a != null && ";
                        }
                        switch($contentsType){
                            case "String":
                            case "Number":
                            case "Boolean":
                            case "Symbol":
                                $contentsTypeChecking .= "a?.constructor != $contentsType";
                                break;
                            default:
                                $contentsTypeChecking .= "!(a instanceof $contentsType)";
                                break;
                        }
                        $typeChecking .= "\nif($variable?.some?.($contentsTypeChecking)){\n    throw TypeError(\"Expected parameter $variable to only contain values of type $contentsType";
                        if($contentsIsNullable){
                            $typeChecking .= " or null";
                            if($contentsIsStrictNullable){
                                $typeChecking .= "/undefined";
                            }
                        }
                        $typeChecking .= ", got \" + $variable?.find?.($contentsTypeChecking)?.constructor?.name);\n}\n";
                    }
                }
                $code = substr_replace($code, $typeChecking, $j + strlen($declarationEndMatch[0]), 0);
                //No need to adjust i and j, it only deletes stuff after these positions
            }
        }
    }
    return $code;
}
