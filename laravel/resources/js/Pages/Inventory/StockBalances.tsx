import React from 'react';
import AppLayout from '../../Components/AppLayout';
import { Head } from '@inertiajs/react';

interface Product {
    id: string;
    code: string;
    name: string;
    type: string;
}

interface UnitOfMeasure {
    id: string;
    code: string;
    name: string;
}

interface StockBalance {
    id: string;
    product_id: string;
    unit_of_measure_id: string;
    currency: string;
    quantity_e6: number;
    valuation_amount_minor: number;
    avg_unit_cost_e6: number;
    product?: Product;
    unit_of_measure?: UnitOfMeasure;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    balances: PaginatedData<StockBalance>;
}

export default function StockBalances({ balances }: Props) {
    const formatQty = (qtyE6: number) => {
        const val = qtyE6 / 1000000;
        return val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 });
    };

    const formatMoney = (minor: number, currency: string) => {
        const val = minor / 100;
        return `${currency} ${val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    const formatAvgCost = (avgE6: number, currency: string) => {
        const val = avgE6 / 1000000;
        return `${currency} ${val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 6 })}`;
    };

    return (
        <AppLayout active="inventory-balances.index">
            <Head title="Stock Balances / أرصدة المخزون" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Stock Balances / أرصدة المخزون</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Moving weighted average physical inventory balances and valuation.
                        </p>
                    </div>
                </div>

                <div className="bg-white shadow rounded-lg overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product / المنتج
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    UOM / وحدة القياس
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantity / الكمية
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Avg Unit Cost / متوسط التكلفة
                                </th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Valuation / إجمالي التقييم
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {balances.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-6 py-8 text-center text-sm text-gray-500">
                                        No stock balances recorded yet. / لا توجد أرصدة مخزون مسجلة بعد.
                                    </td>
                                </tr>
                            ) : (
                                balances.data.map((b) => (
                                    <tr key={b.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {b.product ? `${b.product.code} - ${b.product.name}` : b.product_id}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {b.unit_of_measure ? `${b.unit_of_measure.code}` : b.unit_of_measure_id}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900">
                                            {formatQty(b.quantity_e6)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">
                                            {formatAvgCost(b.avg_unit_cost_e6, b.currency)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900">
                                            {formatMoney(b.valuation_amount_minor, b.currency)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
