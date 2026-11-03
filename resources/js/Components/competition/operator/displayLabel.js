const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
const arabicDigits = '٠١٢٣٤٥٦٧٨٩';
const numberWords = { یک: 1, دو: 2, سه: 3, چهار: 4, پنج: 5, شش: 6, هفت: 7, هشت: 8, نه: 9, ده: 10 };
const generatedLabels = { مرحله: 'ROUND', زمین: 'COURT', فرم: 'FORM', پومسه: 'POOMSAE' };

export function displayLabel(value) {
    if (!value) return value;

    const normalized = String(value).replace(/[۰-۹٠-٩]/g, digit => {
        const persian = persianDigits.indexOf(digit);
        return String(persian >= 0 ? persian : arabicDigits.indexOf(digit));
    }).trim();
    const match = /^(مرحله|زمین|فرم|پومسه)\s+(.+)$/.exec(normalized);
    if (!match) return value;

    const number = /^\d+$/.test(match[2]) ? match[2] : numberWords[match[2]];
    return number ? `${generatedLabels[match[1]]} ${number}` : value;
}
