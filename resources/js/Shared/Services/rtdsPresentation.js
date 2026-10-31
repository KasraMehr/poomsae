export const hasPublishedRanking = (category) => Boolean(category?.completed && category.placements?.length);

export const courtResultFor = (focus, official) => {
    if (!focus || focus.bout.status !== 'completed' || focus.bout.winner_entry_id == null
        || focus.bout.entries?.length !== 2) return null;

    const { category, bout } = focus;
    if (String(official?.category_id) === String(category.id)
        && official?.bout_ids?.some(id => String(id) === String(bout.id))) return official;

    const winnerId = bout.winner_entry_id;
    return {
        status: 'completed', category_id: category.id, round_count: 1,
        bout_ids: [bout.id], winner_entry_id: winnerId,
        winner_total: bout.totals?.[winnerId] ?? null,
        total_max: category.rules?.aggregation === 'sum_two_forms' ? 10 * category.forms_per_round : 10,
    };
};
