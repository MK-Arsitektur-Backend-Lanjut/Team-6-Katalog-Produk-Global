import http from 'k6/http';
import { check, sleep } from 'k6';


export const options = {
  stages: [
    { duration: '2m', target: 2000 },  
    { duration: '5m', target: 10000 },  
    { duration: '1m', target: 0 },      
  ],
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


  sleep(Math.random() * 2 + 1);
}
