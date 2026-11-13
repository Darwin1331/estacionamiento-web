export function subscriptionNotice(subscription) {
    if (!subscription) return '';
    if (subscription.status === 'active') {
        return `Abono vigente #${subscription.id} (${subscription.starts_on} al ${subscription.ends_on}). Información de vigencia; se mantiene el cobro normal por estancia.`;
    }
    if (subscription.status === 'ambiguous') {
        return 'Existen varios abonos vigentes para este vehículo. Revisa los registros; se mantiene el cobro normal por estancia.';
    }
    return 'Sin abono vigente verificable para este vehículo. Se aplica la tarifa por estancia.';
}
