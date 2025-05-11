export default {
    semi: false,
    useTabs: false,
    tabWidth: 4,
    printWidth: 120,
    singleQuote: true,
    trailingComma: "es5",
    endOfLine: "lf",
    twigAlwaysBreakObjects: false,
    twigMultiTags: [
        "nav,endnav",
        "switch,case,default,endswitch",
        "ifchildren,endifchildren",
        "cache,endcache",
        "js,endjs",
    ],
    plugins: ["prettier-plugin-tailwindcss", "@zackad/prettier-plugin-twig"],
}
