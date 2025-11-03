import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { PropsWithChildren } from 'react';

interface StatsCardProps {
  label: string;
  value: string | number;
  subtext?: string;
  accent?: boolean; // if true, emphasize value with accent text color
  className?: string;
}

export function StatsCard({ label, value, subtext, accent, className }: StatsCardProps) {
  return (
    <Card className={cn('p-5 border-none shadow-sm', className)}>
      <p className="text-xs text-gray-500">{label}</p>
      <p className={cn('mt-1 text-3xl font-semibold', 'text-gray-900')}>{value}</p>
      {subtext ? <p className="mt-1 text-xs text-gray-500">{subtext}</p> : null}
    </Card>
  );
}
