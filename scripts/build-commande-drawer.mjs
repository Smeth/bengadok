const fs = require('fs');

const lines = fs.readFileSync('resources/js/pages/Commandes/Index.vue', 'utf8').split(/\r?\n/);
let templateLines = lines.slice(1189, 2540).map((l) => l.replace(/^        /, ''));

templateLines = templateLines.map((l) =>
    l
        .replace('@click="confirmAnnulerEtRelancer"', '@click="onAnnulerEtRelancer"')
        .replace('@click="openRelancerModal"', '@click="$emit(\'open-relancer\')"')
        .replace('@click="showRecuModal = true"', '@click="$emit(\'open-recu\')"'),
);

const script = fs.readFileSync(
    'resources/js/components/commandes/_drawer_script.txt',
    'utf8',
);
const out =
    script + '\n<template>\n' + templateLines.join('\n') + '\n</template>\n';

fs.writeFileSync(
    'resources/js/components/commandes/CommandeDetailDrawer.vue',
    out,
    'utf8',
);

console.log('CommandeDetailDrawer.vue written:', templateLines.length, 'lines');
