import http from 'k6/http';
import { check } from 'k6';

export const options = {
  scenarios: {
    organic_traffic: {
      executor: 'shared-iterations',
      vus: 10000,           
      iterations: 10000,   
      maxDuration: '2m',   
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<5000'],   
    http_req_failed: ['rate<0.05'],
  },
};

const BASE_URL = 'http://localhost:8000/api/v1/catalog/products';

export default function () {
  const resNormal = http.get(`${BASE_URL}?per_page=50`);
  check(resNormal, {
    'GET normal (50 data) status 200': (r) => r.status === 200,
  });
}
