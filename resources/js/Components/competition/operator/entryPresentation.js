export function getEntryMemberNames(entry, profile) {
    const seenMemberIds = new Set();
    const seenNamesWithoutIds = new Set();
    const memberNames = (profile?.members ?? [])
        .filter(member => {
            const name = member?.short_name || member?.name;

            if (typeof name !== 'string' || name.trim() === '') {
                return false;
            }

            if (member.id !== undefined && member.id !== null) {
                if (seenMemberIds.has(member.id)) {
                    return false;
                }

                seenMemberIds.add(member.id);

                return true;
            }

            const normalizedName = name.trim().toLocaleLowerCase();

            if (seenNamesWithoutIds.has(normalizedName)) {
                return false;
            }

            seenNamesWithoutIds.add(normalizedName);

            return true;
        })
        .map(member => (member.short_name || member.name).trim());

    if (memberNames.length > 0) {
        return memberNames;
    }

    const fallbackName = profile?.short_name || entry?.short_name || entry?.name;

    return typeof fallbackName === 'string' && fallbackName.trim() !== ''
        ? [fallbackName.trim()]
        : [];
}

export function getEntrySideClass(entry, index) {
    const side = String(entry?.side ?? '').toLowerCase();

    if (['hong', 'red'].includes(side)) {
        return 'hong';
    }

    if (['chung', 'blue'].includes(side)) {
        return 'chung';
    }

    return index === 1 ? 'hong' : 'chung';
}
