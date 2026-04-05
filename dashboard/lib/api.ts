const API = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

async function request(path: string, options: RequestInit = {}) {
  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : null;

  const res = await fetch(`${API}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...((options.headers as Record<string, string>) || {}),
    },
  });

  if (res.status === 401) {
    if (typeof window !== 'undefined') {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    throw new Error('غير مصرح');
  }

  const data = await res.json();
  if (!res.ok) throw new Error(data.message || 'خطأ');
  return data;
}

// Auth
export const sendOtp = (phone: string) =>
  request('/auth/otp/send', { method: 'POST', body: JSON.stringify({ phone }) });

export const verifyOtp = (phone: string, code: string, session_token?: string) =>
  request('/auth/otp/verify', { method: 'POST', body: JSON.stringify({ phone, code, session_token }) });

// Businesses
export const getBusinesses = () => request('/businesses');

// Services
export const getServices = (bizId: string) => request(`/businesses/${bizId}/services`);
export const createService = (bizId: string, data: Record<string, unknown>) =>
  request(`/businesses/${bizId}/services`, { method: 'POST', body: JSON.stringify(data) });
export const updateService = (bizId: string, svcId: string, data: Record<string, unknown>) =>
  request(`/businesses/${bizId}/services/${svcId}`, { method: 'PUT', body: JSON.stringify(data) });
export const toggleService = (bizId: string, svcId: string, active: boolean) =>
  request(`/businesses/${bizId}/services/${svcId}/toggle`, { method: 'PATCH', body: JSON.stringify({ active }) });

// Stats
export const getStats = (bizId: string) => request(`/businesses/${bizId}/stats`);

// Ali Chat (authenticated)
export const sendToAli = (message: string, businessId?: string) =>
  request('/ali/chat', { method: 'POST', body: JSON.stringify({ message, business_id: businessId }) });

// Ali Chat (guest — direct fetch, no auth redirect)
export async function sendToAliGuest(message: string, sessionToken: string, businessId?: string) {
  const res = await fetch(`${API}/guest/ali/chat`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ message, session_token: sessionToken, business_id: businessId }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.message || 'خطأ');
  return data;
}
