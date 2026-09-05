const fs = require('fs');

const indexPath = 'resources/js/pages/DokPharma/Index.vue';
const lines = fs.readFileSync(indexPath, 'utf8').split(/\r?\n/);

const tabs = [
    {
        name: 'DokPharmaOngletNouvelles',
        start: 592,
        end: 1147,
        script: 'resources/js/components/dok-pharma/_onglet_nouvelles_script.txt',
    },
    {
        name: 'DokPharmaOngletEnAttente',
        start: 1149,
        end: 1470,
        script: 'resources/js/components/dok-pharma/_onglet_en_attente_script.txt',
    },
    {
        name: 'DokPharmaOngletAPreparer',
        start: 1471,
        end: 1842,
        script: 'resources/js/components/dok-pharma/_onglet_a_preparer_script.txt',
    },
    {
        name: 'DokPharmaOngletLivrees',
        start: 1843,
        end: 2183,
        script: 'resources/js/components/dok-pharma/_onglet_livrees_script.txt',
    },
];

for (const tab of tabs) {
    let templateLines = lines
        .slice(tab.start, tab.end + 1)
        .map((l) => l.replace(/^                /, ''));

    // Remove outer <template v-if/v-else-if> wrappers
    templateLines = templateLines.filter(
        (l, i, arr) =>
            !l.includes('<template v-if="onglet ===') &&
            !l.includes('<template v-else-if="onglet ===') &&
            !(l.trim() === '</template>' && i === arr.length - 1),
    );

    const script = fs.readFileSync(tab.script, 'utf8');
    const out =
        script +
        '\n<template>\n' +
        templateLines.join('\n') +
        '\n</template>\n';

    fs.writeFileSync(
        `resources/js/components/dok-pharma/${tab.name}.vue`,
        out,
        'utf8',
    );
    console.log(tab.name, templateLines.length, 'lines');
}
