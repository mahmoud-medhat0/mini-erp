export type NotificationItem = {
  id: string;
  type: string;
  targetRef: string | null;
  read: boolean;
  at: string | null;
};

export type SharedPageProps = {
  auth: {
    user: null | {
      id: number | string;
      name: string;
      email: string;
      locale?: string;
      theme?: string;
    };
    permissions: string[];
  };
  locale: 'en' | 'ar' | string;
  direction: 'ltr' | 'rtl';
  theme: 'light' | 'dark' | string;
  notifications: {
    unreadCount: number;
    recent?: NotificationItem[];
  };
  flash: {
    success?: string | null;
    error?: string | null;
  };
};
